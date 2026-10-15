<?php

use App\Models\User;

beforeEach(function () {
    // Tests run on the null broadcaster, signing private channels needs a Pusher-compatible one
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    require base_path('routes/channels.php');

    $this->user = User::factory()->create();
});

describe('POST /api/broadcast/auth', function () {
    it('signs the private user channel for its own user', function () {
        $response = $this->actingAs($this->user)->postJson('/api/broadcast/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-user.{$this->user->id}",
        ]);

        $response->assertOk()->assertJsonStructure(['auth']);
        expect($response->json('auth'))->toStartWith('test-key:');
    });

    it('denies the private user channel of someone else', function () {
        $other = User::factory()->create();

        $response = $this->actingAs($this->user)->postJson('/api/broadcast/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-user.{$other->id}",
        ]);

        $response->assertForbidden();
    });

    it('denies private channels for guests', function () {
        $response = $this->postJson('/api/broadcast/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-user.{$this->user->id}",
        ]);

        $response->assertForbidden();
    });
});
