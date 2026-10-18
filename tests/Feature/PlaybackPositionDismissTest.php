<?php

use App\Models\Liveset;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->liveset = Liveset::factory()->create();
    $this->user->playbackPositions()->create(['liveset_id' => $this->liveset->id, 'client_id' => 'phone', 'position' => 120]);
});

function positionsFrom(string $clientId)
{
    return test()->actingAs(test()->user, 'sanctum')
        ->withHeader('X-Client-ID', $clientId)
        ->getJson('/api/playback/positions');
}

it('hides a dismissed position from other devices', function () {
    $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Client-ID', 'tv')
        ->postJson("/api/playback/positions/{$this->liveset->id}/dismiss", ['client_id' => 'phone'])
        ->assertOk();

    positionsFrom('tv')->assertOk()->assertJsonCount(0, 'positions');
    positionsFrom('laptop')->assertOk()->assertJsonCount(0, 'positions');
});

it('keeps a dismissed position for the device it belongs to', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/playback/positions/{$this->liveset->id}/dismiss", ['client_id' => 'phone'])
        ->assertOk();

    positionsFrom('phone')->assertOk()->assertJsonCount(1, 'positions');
});

it('brings it back once that device plays the set again', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/playback/positions/{$this->liveset->id}/dismiss", ['client_id' => 'phone'])
        ->assertOk();

    $this->actingAs($this->user, 'sanctum')
        ->withHeader('X-Client-ID', 'phone')
        ->putJson("/api/playback/positions/{$this->liveset->id}", ['position' => 180])
        ->assertOk();

    positionsFrom('tv')->assertOk()->assertJsonCount(1, 'positions')->assertJsonPath('positions.0.position', 180);
});

it('only dismisses the given device', function () {
    $this->user->playbackPositions()->create(['liveset_id' => $this->liveset->id, 'client_id' => 'laptop', 'position' => 60]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/playback/positions/{$this->liveset->id}/dismiss", ['client_id' => 'phone'])
        ->assertOk();

    positionsFrom('tv')->assertOk()->assertJsonCount(1, 'positions')->assertJsonPath('positions.0.position', 60);
});

it('needs a login', function () {
    $this->postJson("/api/playback/positions/{$this->liveset->id}/dismiss", ['client_id' => 'phone'])->assertUnauthorized();
});
