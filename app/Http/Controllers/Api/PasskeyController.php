<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePasskeyRequest;
use App\Services\PasskeyChallenges;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\PublicKeyCredentialCreationOptions;

/**
 * The user's passkeys, for the app (Bearer token). The web uses the package's session routes.
 */
class PasskeyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'passkeys' => $request->user()->passkeys()->latest()->get()->map(fn (Passkey $passkey) => $this->format($passkey)),
        ]);
    }

    /**
     * Start adding a passkey. Excludes the user's existing ones, so the same authenticator can't be added twice.
     */
    public function options(Request $request, GenerateRegistrationOptions $generate, PasskeyChallenges $challenges): JsonResponse
    {
        $options = $generate($request->user());

        return response()->json([
            'challenge_id' => $challenges->put($options, 'register', $request->user()->id),
            'options' => WebAuthn::toBrowserArray($options),
        ]);
    }

    public function store(StorePasskeyRequest $request, StorePasskey $store, PasskeyChallenges $challenges): JsonResponse
    {
        $options = $challenges->pull($request->validated('challenge_id'), 'register', PublicKeyCredentialCreationOptions::class, $request->user()->id);
        if ($options === null) {
            return response()->json([
                'message' => 'The passkey challenge expired, try again.',
                'errors' => ['challenge_id' => ['The passkey challenge expired, try again.']],
            ], 422);
        }

        try {
            $passkey = $store($request->user(), $request->validated('name'), $request->credential(), $options);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Unable to add this passkey.',
                'errors' => ['credential' => ['Unable to add this passkey.']],
            ], 422);
        }

        return response()->json(['passkey' => $this->format($passkey)], 201);
    }

    public function destroy(Request $request, DeletePasskey $delete, int $id): JsonResponse
    {
        $delete($request->user(), $request->user()->passkeys()->findOrFail($id));

        return response()->json(['message' => 'Passkey removed.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function format(Passkey $passkey): array
    {
        return [
            'id' => $passkey->id,
            'name' => $passkey->name,
            'authenticator' => $passkey->authenticator,
            'created_at' => $passkey->created_at->toIso8601String(),
            'last_used_at' => $passkey->last_used_at?->toIso8601String(),
        ];
    }
}
