<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ParsesPasskeyCredential;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add a passkey from the app, answers a challenge from GET /api/passkeys/options
 */
class StorePasskeyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            ...$this->credentialRules(),
        ];
    }
}
