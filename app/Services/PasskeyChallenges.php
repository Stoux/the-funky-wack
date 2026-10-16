<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Passkeys\Support\WebAuthn;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * Passkey challenges for the API. The web flow keeps them in the session, the app has none,
 * so they live in the cache under a random id: single use, for one purpose (and one user when registering).
 */
class PasskeyChallenges
{
    public const TTL_MINUTES = 5;

    public function put(PublicKeyCredentialRequestOptions|PublicKeyCredentialCreationOptions $options, string $purpose, ?int $userId = null): string
    {
        $challengeId = Str::random(40);

        Cache::put($this->key($challengeId), [
            'purpose' => $purpose,
            'user_id' => $userId,
            'options' => WebAuthn::toJson($options),
        ], now()->addMinutes(self::TTL_MINUTES));

        return $challengeId;
    }

    /**
     * The options for a challenge, once. Null when unknown, expired, used, or meant for something else.
     *
     * @template T of PublicKeyCredentialRequestOptions|PublicKeyCredentialCreationOptions
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public function pull(string $challengeId, string $purpose, string $class, ?int $userId = null): mixed
    {
        $challenge = Cache::pull($this->key($challengeId));

        if (! is_array($challenge) || $challenge['purpose'] !== $purpose || $challenge['user_id'] !== $userId) {
            return null;
        }

        return WebAuthn::fromJson($challenge['options'], $class);
    }

    private function key(string $challengeId): string
    {
        return "passkey-challenge:{$challengeId}";
    }
}
