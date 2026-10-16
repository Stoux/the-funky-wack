<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\PublicKeyCredential;

/**
 * The credential is Credential Manager's registrationResponseJson / authenticationResponseJson as-is
 */
trait ParsesPasskeyCredential
{
    private ?PublicKeyCredential $publicKeyCredential = null;

    /**
     * @return array<string, array<int, string>>
     */
    protected function credentialRules(): array
    {
        return [
            'credential' => ['required', 'array'],
            'credential.id' => ['required', 'string'],
            'credential.rawId' => ['required', 'string'],
            'credential.type' => ['required', 'string', 'in:public-key'],
            'credential.response' => ['required', 'array'],
        ];
    }

    protected function passedValidation(): void
    {
        try {
            $this->publicKeyCredential = WebAuthn::fromJson(
                json_encode($this->input('credential')) ?: '{}',
                PublicKeyCredential::class
            );
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'credential' => 'Invalid credential format.',
            ]);
        }
    }

    public function credential(): PublicKeyCredential
    {
        return $this->publicKeyCredential;
    }
}
