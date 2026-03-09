<?php

use App\Models\PlayHistory;
use App\Models\User;
use App\Services\ListenAlongService;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();

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
        'session_id' => 'host-session',
        'liveset_id' => $this->liveset->id,
        'started_at_position' => 0,
        'quality' => 'hq',
        'platform' => 'web',
    ]);

    $this->room = $this->service->createRoom($this->playHistory);
    $this->clientId = 'test-client-id-123';
});

test('GET /api/live/sessions returns active sessions', function () {
    $response = $this->getJson('/api/live/sessions');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'sessions')
        ->assertJsonPath('sessions.0.channel_token', $this->room->channel_token)
        ->assertJsonPath('sessions.0.host.name', 'Test Host')
        ->assertJsonPath('sessions.0.liveset.title', 'Test Liveset');
});

test('GET /api/live/sessions returns empty when no active rooms', function () {
    $this->service->endRoom($this->room);

    $response = $this->getJson('/api/live/sessions');

    $response->assertSuccessful()
        ->assertJsonCount(0, 'sessions');
});

test('GET /api/live/rooms/{token}/state returns room state', function () {
    $response = $this->getJson("/api/live/rooms/{$this->room->channel_token}/state");

    $response->assertSuccessful()
        ->assertJsonPath('channel_token', $this->room->channel_token)
        ->assertJsonPath('host.name', 'Test Host');
});

test('GET /api/live/rooms/{token}/state returns 404 for invalid token', function () {
    $response = $this->getJson('/api/live/rooms/nonexistent/state');

    $response->assertNotFound();
});

test('POST /api/live/rooms/{token}/join creates synced listener', function () {
    $response = $this->postJson("/api/live/rooms/{$this->room->channel_token}/join", [
        'mode' => 'synced',
    ], ['X-Client-ID' => $this->clientId]);

    $response->assertSuccessful();
    expect($this->room->activeSyncedListeners()->count())->toBe(1);
});

test('POST /api/live/rooms/{token}/join creates independent listener', function () {
    $response = $this->postJson("/api/live/rooms/{$this->room->channel_token}/join", [
        'mode' => 'independent',
    ], ['X-Client-ID' => $this->clientId]);

    $response->assertSuccessful();

    // Independent listeners are immediately left
    expect($this->room->activeSyncedListeners()->count())->toBe(0);
});

test('POST /api/live/rooms/{token}/join validates mode', function () {
    $response = $this->postJson("/api/live/rooms/{$this->room->channel_token}/join", [
        'mode' => 'invalid',
    ], ['X-Client-ID' => $this->clientId]);

    $response->assertUnprocessable();
});

test('POST /api/live/rooms/{token}/join returns 404 for invalid token', function () {
    $response = $this->postJson('/api/live/rooms/nonexistent/join', [
        'mode' => 'synced',
    ], ['X-Client-ID' => $this->clientId]);

    $response->assertNotFound();
});

test('POST /api/live/rooms/{token}/join requires X-Client-ID', function () {
    $response = $this->postJson("/api/live/rooms/{$this->room->channel_token}/join", [
        'mode' => 'synced',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'X-Client-ID header is required.');
});

test('POST /api/live/rooms/{token}/leave removes listener', function () {
    $this->postJson("/api/live/rooms/{$this->room->channel_token}/join", [
        'mode' => 'synced',
    ], ['X-Client-ID' => $this->clientId]);

    $response = $this->postJson(
        "/api/live/rooms/{$this->room->channel_token}/leave",
        [],
        ['X-Client-ID' => $this->clientId]
    );

    $response->assertSuccessful();
    expect($this->room->activeSyncedListeners()->count())->toBe(0);
});

test('POST /api/live/rooms/{token}/leave requires X-Client-ID', function () {
    $response = $this->postJson("/api/live/rooms/{$this->room->channel_token}/leave");

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'X-Client-ID header is required.');
});

test('POST /api/live/rooms/{token}/detach switches to independent', function () {
    $this->postJson("/api/live/rooms/{$this->room->channel_token}/join", [
        'mode' => 'synced',
    ], ['X-Client-ID' => $this->clientId]);

    $response = $this->postJson(
        "/api/live/rooms/{$this->room->channel_token}/detach",
        [],
        ['X-Client-ID' => $this->clientId]
    );

    $response->assertSuccessful();
    expect($this->room->activeSyncedListeners()->count())->toBe(0);
});

test('POST /api/live/rooms/{token}/detach requires X-Client-ID', function () {
    $response = $this->postJson("/api/live/rooms/{$this->room->channel_token}/detach");

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'X-Client-ID header is required.');
});

test('GET /live page renders', function () {
    $response = $this->get('/live');

    $response->assertSuccessful();
});

test('PUT /api/settings/visibility requires authentication', function () {
    $response = $this->putJson('/api/settings/visibility', [
        'listening_visibility' => 'nobody',
    ]);

    $response->assertUnauthorized();
});

test('PUT /api/settings/visibility updates user setting', function () {
    $response = $this->actingAs($this->user)->putJson('/api/settings/visibility', [
        'listening_visibility' => 'nobody',
    ]);

    $response->assertSuccessful();
    expect($this->user->fresh()->listening_visibility)->toBe('nobody');
});

test('PUT /api/settings/visibility validates value', function () {
    $response = $this->actingAs($this->user)->putJson('/api/settings/visibility', [
        'listening_visibility' => 'invalid',
    ]);

    $response->assertUnprocessable();
});

test('PUT /api/settings/visibility accepts all valid values', function (string $value) {
    $response = $this->actingAs($this->user)->putJson('/api/settings/visibility', [
        'listening_visibility' => $value,
    ]);

    $response->assertSuccessful();
    expect($this->user->fresh()->listening_visibility)->toBe($value);
})->with(['everyone', 'authenticated', 'nobody']);

test('sessions respect host visibility setting', function () {
    $this->user->update(['listening_visibility' => 'nobody']);

    $response = $this->getJson('/api/live/sessions');

    $response->assertSuccessful()
        ->assertJsonPath('sessions.0.host.name', 'Anonymous');
});

test('sessions show host name to authenticated when visibility is authenticated', function () {
    $this->user->update(['listening_visibility' => 'authenticated']);

    $viewer = User::forceCreate([
        'name' => 'Viewer',
        'email' => 'viewer@test.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->actingAs($viewer)->getJson('/api/live/sessions');

    $response->assertSuccessful()
        ->assertJsonPath('sessions.0.host.name', 'Test Host');
});

test('sessions hide host name from guests when visibility is authenticated', function () {
    $this->user->update(['listening_visibility' => 'authenticated']);

    $response = $this->getJson('/api/live/sessions');

    $response->assertSuccessful()
        ->assertJsonPath('sessions.0.host.name', 'Anonymous');
});
