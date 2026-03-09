<?php

use App\Events\ListenAlongHostAction;
use App\Events\LiveSessionsUpdated;
use App\Jobs\EndListenRoomIfStale;
use App\Listeners\PlaybackEventSubscriber;
use App\Models\ListenRoom;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->subscriber = app(PlaybackEventSubscriber::class);

    $this->user = User::forceCreate([
        'name' => 'Host User',
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

    $this->secondLiveset = \App\Models\Liveset::forceCreate([
        'edition_id' => $edition->id,
        'title' => 'Second Liveset',
        'artist_name' => 'Another Artist',
        'duration_in_seconds' => 1800,
    ]);

    $this->sessionId = 'integration-session-123';
});

test('handleStart creates a listen room', function () {
    Event::fake();

    $playHistory = $this->subscriber->handleStart(
        sessionId: $this->sessionId,
        livesetId: $this->liveset->id,
        position: 0,
        quality: 'hq',
        clientId: 'client-1',
        userId: $this->user->id,
    );

    $room = ListenRoom::whereNull('ended_at')->first();
    expect($room)->not->toBeNull()
        ->and($room->host)->not->toBeNull()
        ->and($room->host->play_history_id)->toBe($playHistory->id)
        ->and($room->host->user_id)->toBe($this->user->id);

    Event::assertDispatched(LiveSessionsUpdated::class);
});

test('handleStart on second track updates existing room instead of creating new one', function () {
    Event::fake();

    $firstPlay = $this->subscriber->handleStart(
        sessionId: $this->sessionId,
        livesetId: $this->liveset->id,
        position: 0,
        quality: 'hq',
        clientId: 'client-1',
        userId: $this->user->id,
    );

    $secondPlay = $this->subscriber->handleStart(
        sessionId: $this->sessionId,
        livesetId: $this->secondLiveset->id,
        position: 0,
        quality: 'hq',
        clientId: 'client-1',
        userId: $this->user->id,
    );

    // Should still be just one active room
    expect(ListenRoom::whereNull('ended_at')->count())->toBe(1);

    // Host's play history should be updated to the new track
    $room = ListenRoom::whereNull('ended_at')->first();
    expect($room->host->play_history_id)->toBe($secondPlay->id);
});

test('handleStart on second track broadcasts track change when listeners exist', function () {
    Event::fake();

    $this->subscriber->handleStart(
        sessionId: $this->sessionId,
        livesetId: $this->liveset->id,
        position: 0,
        quality: 'hq',
        clientId: 'client-1',
        userId: $this->user->id,
    );

    // Add a synced listener
    $room = ListenRoom::whereNull('ended_at')->first();
    app(\App\Services\ListenAlongService::class)->joinRoom(
        $room->channel_token, 'listener-client', null, 'synced'
    );

    Event::fake(); // Reset to only capture the track change

    $this->subscriber->handleStart(
        sessionId: $this->sessionId,
        livesetId: $this->secondLiveset->id,
        position: 0,
        quality: 'lossless',
        clientId: 'client-1',
        userId: $this->user->id,
    );

    Event::assertDispatched(ListenAlongHostAction::class, function ($event) {
        return $event->action === 'host.track-change'
            && $event->data['liveset_id'] === $this->secondLiveset->id
            && $event->data['quality'] === 'lossless';
    });
});

test('handleSeek broadcasts host.seek to room', function () {
    Event::fake();

    $playHistory = $this->subscriber->handleStart(
        sessionId: $this->sessionId,
        livesetId: $this->liveset->id,
        position: 0,
        quality: 'hq',
        clientId: 'client-1',
        userId: $this->user->id,
    );

    // Add a synced listener so broadcast fires
    $room = ListenRoom::whereNull('ended_at')->first();
    app(\App\Services\ListenAlongService::class)->joinRoom(
        $room->channel_token, 'listener-client', null, 'synced'
    );

    Event::fake();

    $this->subscriber->handleSeek(
        sessionId: $this->sessionId,
        playHistoryId: $playHistory->id,
        fromPosition: 0,
        toPosition: 120,
    );

    Event::assertDispatched(ListenAlongHostAction::class, function ($event) {
        return $event->action === 'host.seek'
            && $event->data['position'] === 120;
    });
});

test('handlePause broadcasts host.pause to room', function () {
    Event::fake();

    $playHistory = $this->subscriber->handleStart(
        sessionId: $this->sessionId,
        livesetId: $this->liveset->id,
        position: 0,
        quality: 'hq',
        clientId: 'client-1',
        userId: $this->user->id,
    );

    $room = ListenRoom::whereNull('ended_at')->first();
    app(\App\Services\ListenAlongService::class)->joinRoom(
        $room->channel_token, 'listener-client', null, 'synced'
    );

    Event::fake();

    $this->subscriber->handlePause(
        sessionId: $this->sessionId,
        playHistoryId: $playHistory->id,
        position: 60,
    );

    Event::assertDispatched(ListenAlongHostAction::class, function ($event) {
        return $event->action === 'host.pause'
            && $event->data['position'] === 60;
    });
});

test('handleResume broadcasts host.resume to room', function () {
    Event::fake();

    $playHistory = $this->subscriber->handleStart(
        sessionId: $this->sessionId,
        livesetId: $this->liveset->id,
        position: 0,
        quality: 'hq',
        clientId: 'client-1',
        userId: $this->user->id,
    );

    $room = ListenRoom::whereNull('ended_at')->first();
    app(\App\Services\ListenAlongService::class)->joinRoom(
        $room->channel_token, 'listener-client', null, 'synced'
    );

    Event::fake();

    $this->subscriber->handleResume(
        sessionId: $this->sessionId,
        playHistoryId: $playHistory->id,
        position: 60,
    );

    Event::assertDispatched(ListenAlongHostAction::class, function ($event) {
        return $event->action === 'host.resume'
            && $event->data['position'] === 60;
    });
});

test('handleStop ends the listen room', function () {
    Event::fake();

    $playHistory = $this->subscriber->handleStart(
        sessionId: $this->sessionId,
        livesetId: $this->liveset->id,
        position: 0,
        quality: 'hq',
        clientId: 'client-1',
        userId: $this->user->id,
    );

    $room = ListenRoom::whereNull('ended_at')->first();
    expect($room)->not->toBeNull();

    $this->subscriber->handleStop(
        sessionId: $this->sessionId,
        playHistoryId: $playHistory->id,
        position: 300,
        durationListened: 300,
    );

    // Room without listeners gets deleted
    expect(ListenRoom::find($room->id))->toBeNull();
});

test('handleStop broadcasts host.stop when listeners exist', function () {
    Event::fake();

    $playHistory = $this->subscriber->handleStart(
        sessionId: $this->sessionId,
        livesetId: $this->liveset->id,
        position: 0,
        quality: 'hq',
        clientId: 'client-1',
        userId: $this->user->id,
    );

    $room = ListenRoom::whereNull('ended_at')->first();
    app(\App\Services\ListenAlongService::class)->joinRoom(
        $room->channel_token, 'listener-client', null, 'synced'
    );

    Event::fake();

    $this->subscriber->handleStop(
        sessionId: $this->sessionId,
        playHistoryId: $playHistory->id,
        position: 300,
        durationListened: 300,
    );

    Event::assertDispatched(ListenAlongHostAction::class, function ($event) {
        return $event->action === 'host.stop';
    });

    // Room with listeners is kept but ended
    $room->refresh();
    expect($room->ended_at)->not->toBeNull();
});

test('no broadcast when room has no synced listeners', function () {
    Event::fake();

    $playHistory = $this->subscriber->handleStart(
        sessionId: $this->sessionId,
        livesetId: $this->liveset->id,
        position: 0,
        quality: 'hq',
        clientId: 'client-1',
        userId: $this->user->id,
    );

    Event::fake(); // Reset

    $this->subscriber->handleSeek(
        sessionId: $this->sessionId,
        playHistoryId: $playHistory->id,
        fromPosition: 0,
        toPosition: 120,
    );

    Event::assertNotDispatched(ListenAlongHostAction::class);
});

test('track switch: stop then start keeps the same room', function () {
    Event::fake();
    Queue::fake();

    // Host starts playing — creates a room
    $playHistory = $this->subscriber->handleStart(
        sessionId: $this->sessionId,
        livesetId: $this->liveset->id,
        position: 0,
        quality: 'hq',
        clientId: 'client-1',
        userId: $this->user->id,
    );

    $room = ListenRoom::whereNull('ended_at')->first();
    expect($room)->not->toBeNull();

    // Host stops (track switch — job is queued but not executed)
    $this->subscriber->handleStop(
        sessionId: $this->sessionId,
        playHistoryId: $playHistory->id,
        position: 300,
        durationListened: 300,
    );

    Queue::assertPushed(EndListenRoomIfStale::class);

    // Room should still be active (job hasn't run yet)
    $room->refresh();
    expect($room->ended_at)->toBeNull();

    // Host starts new track — cancels the pending end
    $newPlayHistory = $this->subscriber->handleStart(
        sessionId: $this->sessionId,
        livesetId: $this->secondLiveset->id,
        position: 0,
        quality: 'hq',
        clientId: 'client-1',
        userId: $this->user->id,
    );

    // Still only one active room (same room, updated track)
    expect(ListenRoom::whereNull('ended_at')->count())->toBe(1);

    $room->refresh();
    expect($room->ended_at)->toBeNull();
    expect($room->host->play_history_id)->toBe($newPlayHistory->id);
});

test('handleStart skips room creation when client is an active listener', function () {
    Event::fake();

    // Host A starts playing — creates a room
    $hostPlayHistory = $this->subscriber->handleStart(
        sessionId: 'host-session',
        livesetId: $this->liveset->id,
        position: 0,
        quality: 'hq',
        clientId: 'host-client',
        userId: $this->user->id,
    );

    $hostRoom = ListenRoom::whereNull('ended_at')->first();

    // Listener joins Host A's room
    app(\App\Services\ListenAlongService::class)->joinRoom(
        $hostRoom->channel_token, 'listener-client', null, 'synced'
    );

    // Listener starts playing (via listen-along) — should NOT create a new room
    $listenerPlayHistory = $this->subscriber->handleStart(
        sessionId: 'listener-session',
        livesetId: $this->liveset->id,
        position: 0,
        quality: 'hq',
        clientId: 'listener-client',
        userId: null,
    );

    // Still only one active room
    expect(ListenRoom::whereNull('ended_at')->count())->toBe(1);
});

test('full lifecycle: start, seek, pause, resume, stop', function () {
    Event::fake();

    // Host starts playing
    $playHistory = $this->subscriber->handleStart(
        sessionId: $this->sessionId,
        livesetId: $this->liveset->id,
        position: 0,
        quality: 'hq',
        clientId: 'client-1',
        userId: $this->user->id,
    );

    $room = ListenRoom::whereNull('ended_at')->first();
    expect($room)->not->toBeNull();

    // Listener joins
    app(\App\Services\ListenAlongService::class)->joinRoom(
        $room->channel_token, 'listener-client', null, 'synced'
    );

    expect($room->activeSyncedListeners()->count())->toBe(1);

    Event::fake();

    // Host seeks
    $this->subscriber->handleSeek($this->sessionId, $playHistory->id, 0, 100);
    Event::assertDispatched(ListenAlongHostAction::class, fn ($e) => $e->action === 'host.seek');

    Event::fake();

    // Host pauses
    $this->subscriber->handlePause($this->sessionId, $playHistory->id, 100);
    Event::assertDispatched(ListenAlongHostAction::class, fn ($e) => $e->action === 'host.pause');

    Event::fake();

    // Host resumes
    $this->subscriber->handleResume($this->sessionId, $playHistory->id, 100);
    Event::assertDispatched(ListenAlongHostAction::class, fn ($e) => $e->action === 'host.resume');

    Event::fake();

    // Host stops
    $this->subscriber->handleStop($this->sessionId, $playHistory->id, 200, 200);
    Event::assertDispatched(ListenAlongHostAction::class, fn ($e) => $e->action === 'host.stop');

    // Room is ended but kept (had listeners)
    $room->refresh();
    expect($room->ended_at)->not->toBeNull();
    expect($room->activeMembers()->count())->toBe(0);
});
