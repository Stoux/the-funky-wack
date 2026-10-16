<?php

use App\Models\User;
use App\Models\UserDevice;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Passkey;
use ParagonIE\ConstantTime\Base64UrlSafe;

/**
 * What Credential Manager posts, the signature itself is checked by the (mocked) package actions
 */
function credentialPayload(): array
{
    $credentialId = Base64UrlSafe::encodeUnpadded(random_bytes(16));
    $clientData = json_encode(['type' => 'webauthn.get', 'challenge' => 'Y2hhbGxlbmdl', 'origin' => 'android:apk-key-hash:test']);

    return [
        'id' => $credentialId,
        'rawId' => $credentialId,
        'type' => 'public-key',
        'response' => [
            'clientDataJSON' => Base64UrlSafe::encodeUnpadded($clientData),
            // rpIdHash (32 bytes), flags (user present + verified), sign counter (4 bytes)
            'authenticatorData' => Base64UrlSafe::encodeUnpadded(str_repeat("\0", 32)."\x05".pack('N', 1)),
            'signature' => Base64UrlSafe::encodeUnpadded(random_bytes(64)),
            'userHandle' => Base64UrlSafe::encodeUnpadded('user-handle'),
        ],
    ];
}

function apiPasskey(User $user, string $name = 'Pixel 8'): Passkey
{
    return $user->passkeys()->create([
        'name' => $name,
        'credential_id' => Base64UrlSafe::encodeUnpadded(random_bytes(16)),
        'credential' => [],
    ]);
}

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('signing in with a passkey from the app', function () {
    it('hands out options with a challenge id, for any passkey', function () {
        $this->getJson('/api/auth/passkey/options')
            ->assertOk()
            ->assertJsonStructure(['challenge_id', 'options' => ['challenge', 'rpId', 'userVerification']])
            ->assertJsonPath('options.allowCredentials', []);
    });

    it('answers with a token and registers the device, like a password login', function () {
        $passkey = apiPasskey($this->user);
        $this->mock(VerifyPasskey::class)->shouldReceive('__invoke')->once()->andReturn($passkey);
        $challengeId = $this->getJson('/api/auth/passkey/options')->json('challenge_id');

        $response = $this->withHeader('X-Client-ID', 'pixel-8-client')
            ->postJson('/api/auth/passkey', [
                'challenge_id' => $challengeId,
                'credential' => credentialPayload(),
                'device_name' => 'Google Pixel 8',
                'device_type' => 'mobile',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $this->user->id)
            ->assertJsonStructure(['token']);
        expect($this->user->tokens()->first()->name)->toBe('Google Pixel 8')
            ->and(UserDevice::where('user_id', $this->user->id)->where('client_id', 'pixel-8-client')->exists())->toBeTrue();
    });

    it('uses a challenge only once', function () {
        $this->mock(VerifyPasskey::class)->shouldReceive('__invoke')->once()->andReturn(apiPasskey($this->user));
        $challengeId = $this->getJson('/api/auth/passkey/options')->json('challenge_id');
        $payload = ['challenge_id' => $challengeId, 'credential' => credentialPayload()];

        $this->postJson('/api/auth/passkey', $payload)->assertOk();
        $this->postJson('/api/auth/passkey', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenge_id');
    });

    it('rejects an unknown challenge', function () {
        $this->postJson('/api/auth/passkey', ['challenge_id' => 'made-up', 'credential' => credentialPayload()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenge_id');
    });

    it('answers 401 for a passkey it does not know', function () {
        $this->mock(VerifyPasskey::class)->shouldReceive('__invoke')->andThrow(InvalidPasskeyException::make('Passkey not recognized.'));
        $challengeId = $this->getJson('/api/auth/passkey/options')->json('challenge_id');

        $this->postJson('/api/auth/passkey', ['challenge_id' => $challengeId, 'credential' => credentialPayload()])
            ->assertUnauthorized();
    });

    it('validates the credential shape', function () {
        $challengeId = $this->getJson('/api/auth/passkey/options')->json('challenge_id');

        $this->postJson('/api/auth/passkey', ['challenge_id' => $challengeId, 'credential' => ['type' => 'password']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['credential.id', 'credential.type']);
    });
});

describe('managing passkeys from the app', function () {
    it('needs a token', function () {
        $this->getJson('/api/passkeys')->assertUnauthorized();
        $this->getJson('/api/passkeys/options')->assertUnauthorized();
    });

    it('excludes the passkeys you already have from the options', function () {
        apiPasskey($this->user);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/passkeys/options')
            ->assertOk()
            ->assertJsonStructure(['challenge_id', 'options' => ['challenge', 'rp', 'user', 'pubKeyCredParams', 'authenticatorSelection']])
            ->assertJsonPath('options.authenticatorSelection.residentKey', 'required')
            ->assertJsonCount(1, 'options.excludeCredentials');
    });

    it('adds a passkey', function () {
        $this->mock(StorePasskey::class)->shouldReceive('__invoke')->once()
            ->andReturnUsing(fn (User $user, string $name) => apiPasskey($user, $name));
        $challengeId = $this->actingAs($this->user, 'sanctum')->getJson('/api/passkeys/options')->json('challenge_id');

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/passkeys', ['challenge_id' => $challengeId, 'name' => 'Pixel 8', 'credential' => credentialPayload()])
            ->assertCreated()
            ->assertJsonPath('passkey.name', 'Pixel 8')
            ->assertJsonPath('passkey.last_used_at', null);
    });

    it('does not accept a challenge of another user, or a login challenge', function () {
        $otherChallenge = $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/passkeys/options')->json('challenge_id');
        $loginChallenge = $this->getJson('/api/auth/passkey/options')->json('challenge_id');

        foreach ([$otherChallenge, $loginChallenge] as $challengeId) {
            $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/passkeys', ['challenge_id' => $challengeId, 'name' => 'Pixel 8', 'credential' => credentialPayload()])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('challenge_id');
        }
    });

    it('lists and removes your own passkeys only', function () {
        $mine = apiPasskey($this->user, 'Pixel 8');
        $theirs = apiPasskey(User::factory()->create(), 'Not mine');

        $this->actingAs($this->user, 'sanctum')->getJson('/api/passkeys')
            ->assertOk()
            ->assertJsonCount(1, 'passkeys')
            ->assertJsonPath('passkeys.0.name', 'Pixel 8');

        $this->actingAs($this->user, 'sanctum')->deleteJson("/api/passkeys/{$theirs->id}")->assertNotFound();
        $this->actingAs($this->user, 'sanctum')->deleteJson("/api/passkeys/{$mine->id}")->assertOk();

        expect(Passkey::find($mine->id))->toBeNull()
            ->and(Passkey::find($theirs->id))->not->toBeNull();
    });

    it('renames your own passkeys only, from the app and the website', function () {
        $mine = apiPasskey($this->user, 'Pixel 8');
        $theirs = apiPasskey(User::factory()->create(), 'Not mine');

        $this->actingAs($this->user, 'sanctum')->patchJson("/api/passkeys/{$mine->id}", ['name' => 'Work phone'])
            ->assertOk()
            ->assertJsonPath('passkey.name', 'Work phone');
        $this->actingAs($this->user, 'sanctum')->patchJson("/api/passkeys/{$theirs->id}", ['name' => 'Hijacked'])->assertNotFound();
        $this->actingAs($this->user, 'sanctum')->patchJson("/api/passkeys/{$mine->id}", ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->actingAs($this->user, 'web')->patchJson("/user/passkeys/{$mine->id}", ['name' => 'MacBook'])->assertOk();

        expect($mine->fresh()->name)->toBe('MacBook')
            ->and($theirs->fresh()->name)->toBe('Not mine');
    });

    it('tells the app whether you have a passkey', function () {
        $this->actingAs($this->user, 'sanctum')->getJson('/api/auth/user')->assertJsonPath('has_passkeys', false);

        apiPasskey($this->user);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/auth/user')->assertJsonPath('has_passkeys', true);
    });
});

it('accepts the Android app as a passkey origin next to the site', function () {
    expect(config('passkeys.allowed_origins'))
        ->toContain(config('app.url'))
        ->toContain('android:apk-key-hash:0ovX4oPPkbYD4ESvbA_GtBV37KuM19l4A-qDJjBrvxk')
        ->toContain('android:apk-key-hash:Nap0uuVcQbZSmNIDnazEqvJGqxukQi7BHijHa2ldHBU');
});
