<?php

use App\Models\ListenRoom;
use App\Models\ListenRoomMember;
use App\Models\PlayHistory;
use App\Models\User;
use App\Services\ListenAlongService;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->service = app(ListenAlongService::class);

    $this->user = User::forceCreate([
        'name' => 'Test Host',
        'email' => 'host@test.com',
        'password' => bcrypt('password'),
    ]);

    $edition = \App\Models\Edition::forceCreate([
        'number' => '1',
        'tag_line' => 'Test Edition',
        'date' => now()->toDateString(),
    ]);

    $this->liveset = \App\Models\Liveset::forceCreate([
        'edition_id' => $edition->id,
        'title' => 'Test Liveset',
        'artist_name' => 'Test Artist',
        'duration_in_seconds' => 3600,
    ]);

    $this->playHistory = PlayHistory::forceCreate([
        'user_id' => $this->user->id,
        'session_id' => 'test-session-123',
        'client_id' => 'test-client-123',
        'liveset_id' => $this->liveset->id,
        'started_at_position' => 0,
        'quality' => 'hq',
        'platform' => 'web',
    ]);
});

test('createRoom creates a room with host member', function () {
    Event::fake();

    $room = $this->service->createRoom($this->playHistory);

    expect($room)->toBeInstanceOf(ListenRoom::class)
        ->and($room->channel_token)->toHaveLength(32)
        ->and($room->started_at)->not->toBeNull()
        ->and($room->ended_at)->toBeNull();

    $host = $room->host;
    expect($host)->not->toBeNull()
        ->and($host->role)->toBe('host')
        ->and($host->user_id)->toBe($this->user->id)
        ->and($host->session_id)->toBe('test-session-123')
        ->and($host->play_history_id)->toBe($this->playHistory->id)
        ->and($host->left_at)->toBeNull();
});

test('endRoom sets ended_at and marks members as left', function () {
    Event::fake();

    $room = $this->service->createRoom($this->playHistory);

    // Add a synced listener
    $this->service->joinRoom($room->channel_token, 'listener-client', null, 'synced');

    $this->service->endRoom($room);

    $room->refresh();
    expect($room->ended_at)->not->toBeNull();

    // All members should be marked as left
    expect($room->activeMembers()->count())->toBe(0);
});

test('endRoom deletes room without listeners', function () {
    Event::fake();

    $room = $this->service->createRoom($this->playHistory);
    $roomId = $room->id;

    $this->service->endRoom($room);

    expect(ListenRoom::find($roomId))->toBeNull();
});

test('endRoom keeps room with listener history', function () {
    Event::fake();

    $room = $this->service->createRoom($this->playHistory);

    // Add and then remove a listener
    $this->service->joinRoom($room->channel_token, 'listener-client', null, 'synced');
    $this->service->leaveRoom($room->channel_token, 'listener-client');

    $this->service->endRoom($room);

    $room->refresh();
    expect($room->ended_at)->not->toBeNull();
    expect(ListenRoom::find($room->id))->not->toBeNull();
});

test('joinRoom creates synced listener member', function () {
    Event::fake();

    $room = $this->service->createRoom($this->playHistory);

    $member = $this->service->joinRoom($room->channel_token, 'listener-client', null, 'synced');

    expect($member)->not->toBeNull()
        ->and($member->role)->toBe('listener')
        ->and($member->mode)->toBe('synced')
        ->and($member->left_at)->toBeNull();

    expect($room->activeSyncedListeners()->count())->toBe(1);
});

test('joinRoom creates independent listener with immediate left_at', function () {
    Event::fake();

    $room = $this->service->createRoom($this->playHistory);

    $member = $this->service->joinRoom($room->channel_token, 'listener-client', null, 'independent');

    expect($member)->not->toBeNull()
        ->and($member->role)->toBe('listener')
        ->and($member->mode)->toBe('independent')
        ->and($member->left_at)->not->toBeNull();

    // Independent listener should NOT count as active
    expect($room->activeSyncedListeners()->count())->toBe(0);
});

test('joinRoom returns null for non-existent room', function () {
    Event::fake();

    $member = $this->service->joinRoom('nonexistent-token', 'listener-client');

    expect($member)->toBeNull();
});

test('joinRoom returns null for ended room', function () {
    Event::fake();

    $room = $this->service->createRoom($this->playHistory);
    $this->service->endRoom($room);

    $member = $this->service->joinRoom($room->channel_token, 'listener-client');

    expect($member)->toBeNull();
});

test('leaveRoom sets left_at on the listener', function () {
    Event::fake();

    $room = $this->service->createRoom($this->playHistory);
    $this->service->joinRoom($room->channel_token, 'listener-client', null, 'synced');

    $this->service->leaveRoom($room->channel_token, 'listener-client');

    $member = ListenRoomMember::where('client_id', 'listener-client')->first();
    expect($member->left_at)->not->toBeNull();
    expect($room->activeSyncedListeners()->count())->toBe(0);
});

test('detachListener changes mode to independent and sets left_at', function () {
    Event::fake();

    $room = $this->service->createRoom($this->playHistory);
    $this->service->joinRoom($room->channel_token, 'listener-client', null, 'synced');

    $this->service->detachListener($room->channel_token, 'listener-client');

    $member = ListenRoomMember::where('client_id', 'listener-client')
        ->where('role', 'listener')
        ->first();
    expect($member->mode)->toBe('independent')
        ->and($member->left_at)->not->toBeNull();
});

test('pauseSync sets sync_paused_at on listener', function () {
    Event::fake();

    $room = $this->service->createRoom($this->playHistory);
    $this->service->joinRoom($room->channel_token, 'listener-client', null, 'synced');

    $this->service->pauseSync($room->channel_token, 'listener-client');

    $member = ListenRoomMember::where('client_id', 'listener-client')
        ->where('role', 'listener')
        ->first();
    expect($member->sync_paused_at)->not->toBeNull();
});

test('resumeSync clears sync_paused_at on listener', function () {
    Event::fake();

    $room = $this->service->createRoom($this->playHistory);
    $this->service->joinRoom($room->channel_token, 'listener-client', null, 'synced');
    $this->service->pauseSync($room->channel_token, 'listener-client');

    $this->service->resumeSync($room->channel_token, 'listener-client');

    $member = ListenRoomMember::where('client_id', 'listener-client')
        ->where('role', 'listener')
        ->first();
    expect($member->sync_paused_at)->toBeNull();
});

test('findActiveRoomForSession returns the active room', function () {
    Event::fake();

    $room = $this->service->createRoom($this->playHistory);

    $found = $this->service->findActiveRoomForSession('test-session-123');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($room->id);
});

test('findActiveRoomForSession returns null when no active room', function () {
    Event::fake();

    $found = $this->service->findActiveRoomForSession('nonexistent-session');

    expect($found)->toBeNull();
});

test('findActiveRoomForSession returns null for ended room', function () {
    Event::fake();

    $room = $this->service->createRoom($this->playHistory);
    $this->service->endRoom($room);

    $found = $this->service->findActiveRoomForSession('test-session-123');

    expect($found)->toBeNull();
});

test('updateHostPlayHistory updates the host member', function () {
    Event::fake();

    $room = $this->service->createRoom($this->playHistory);

    $newPlayHistory = PlayHistory::forceCreate([
        'user_id' => $this->user->id,
        'session_id' => 'test-session-123',
        'liveset_id' => $this->liveset->id,
        'started_at_position' => 100,
        'quality' => 'lossless',
        'platform' => 'web',
    ]);

    $this->service->updateHostPlayHistory($room, $newPlayHistory);

    $room->refresh();
    expect($room->host->play_history_id)->toBe($newPlayHistory->id);
});

test('getActiveSessions returns active rooms with host info', function () {
    Event::fake();

    $this->service->createRoom($this->playHistory);

    $sessions = $this->service->getActiveSessions(null, false);

    expect($sessions)->toHaveCount(1)
        ->and($sessions[0]['host']['name'])->toBe('Test Host')
        ->and($sessions[0]['liveset']['title'])->toBe('Test Liveset')
        ->and($sessions[0]['listeners_count'])->toBe(0);
});

test('getActiveSessions excludes ended rooms', function () {
    Event::fake();

    $room = $this->service->createRoom($this->playHistory);
    $this->service->endRoom($room);

    $sessions = $this->service->getActiveSessions(null, false);

    expect($sessions)->toHaveCount(0);
});

test('resolveDisplayName respects visibility settings', function () {
    $this->user->update(['listening_visibility' => 'everyone']);
    expect($this->service->resolveDisplayName($this->user->id, null, false))->toBe('Test Host');

    $this->user->update(['listening_visibility' => 'authenticated']);
    expect($this->service->resolveDisplayName($this->user->id, null, true))->toBe('Test Host');
    expect($this->service->resolveDisplayName($this->user->id, null, false))->toBe('Anonymous');

    $this->user->update(['listening_visibility' => 'nobody']);
    expect($this->service->resolveDisplayName($this->user->id, null, true))->toBe('Anonymous');
});

test('resolveDisplayName always shows own name', function () {
    $this->user->update(['listening_visibility' => 'nobody']);

    expect($this->service->resolveDisplayName($this->user->id, $this->user->id, false))->toBe('Test Host');
});

test('resolveDisplayName returns Anonymous for null user', function () {
    expect($this->service->resolveDisplayName(null, null, false))->toBe('Anonymous');
});

test('cleanupEmptyEndedRooms deletes rooms without listeners', function () {
    Event::fake();

    $room = $this->service->createRoom($this->playHistory);
    $room->update(['ended_at' => now()]);

    $count = $this->service->cleanupEmptyEndedRooms();

    expect($count)->toBe(1);
    expect(ListenRoom::find($room->id))->toBeNull();
});

test('cleanupEmptyEndedRooms keeps rooms with listener history', function () {
    Event::fake();

    $room = $this->service->createRoom($this->playHistory);
    $this->service->joinRoom($room->channel_token, 'listener-client', null, 'synced');
    $this->service->leaveRoom($room->channel_token, 'listener-client');
    $room->update(['ended_at' => now()]);

    $count = $this->service->cleanupEmptyEndedRooms();

    expect($count)->toBe(0);
    expect(ListenRoom::find($room->id))->not->toBeNull();
});

test('endStaleRooms ends rooms with stale host', function () {
    Event::fake();

    $room = $this->service->createRoom($this->playHistory);

    // Add a listener so the room isn't deleted when ended
    $this->service->joinRoom($room->channel_token, 'listener-client', null, 'synced');

    // Make the play history stale (bypass Eloquent timestamps)
    PlayHistory::where('id', $this->playHistory->id)
        ->update(['updated_at' => now()->subMinutes(3)]);

    $count = $this->service->endStaleRooms();

    expect($count)->toBe(1);

    $room->refresh();
    expect($room->ended_at)->not->toBeNull();
});

test('joinRoom ends hosted room when client joins another room', function () {
    Event::fake();

    // Host A creates a room
    $roomA = $this->service->createRoom($this->playHistory);

    // Another user creates a different room
    $otherUser = User::forceCreate([
        'name' => 'Other Host',
        'email' => 'other@test.com',
        'password' => bcrypt('password'),
    ]);
    $otherPlayHistory = PlayHistory::forceCreate([
        'user_id' => $otherUser->id,
        'session_id' => 'other-session',
        'client_id' => 'other-client',
        'liveset_id' => $this->liveset->id,
        'started_at_position' => 0,
        'quality' => 'hq',
        'platform' => 'web',
    ]);
    $roomB = $this->service->createRoom($otherPlayHistory);

    // Host A's client joins Room B as a listener — Room A should be ended
    $member = $this->service->joinRoom(
        $roomB->channel_token,
        'test-client-123',
        $this->user->id,
        'synced'
    );

    expect($member)->not->toBeNull();

    // Room A had no listeners, so endRoom deletes it entirely
    expect(ListenRoom::find($roomA->id))->toBeNull();

    // Room B should still be active
    $roomB->refresh();
    expect($roomB->ended_at)->toBeNull();
});

test('endStaleRooms does not end rooms with recent activity', function () {
    Event::fake();

    $this->service->createRoom($this->playHistory);

    // Play history is fresh (just created)
    $count = $this->service->endStaleRooms();

    expect($count)->toBe(0);
});
