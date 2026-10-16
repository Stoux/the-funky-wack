<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ParsesPasskeyCredential;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Passkey sign in from the app, answers a challenge from GET /api/auth/passkey/options
 */
class PasskeyLoginRequest extends FormRequest
{
    use ParsesPasskeyCredential;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'string', 'max:64'],
            ...$this->credentialRules(),
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'string', 'in:mobile,tablet,tv,car,desktop,other'],
        ];
    }
}
