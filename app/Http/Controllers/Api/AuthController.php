<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PasskeyLoginRequest;
use App\Models\InviteCode;
use App\Models\User;
use App\Services\PasskeyChallenges;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\PublicKeyCredentialRequestOptions;

class AuthController extends Controller
{
    /**
     * Register a new user with an invite code.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
            'invite_code' => ['required', 'string', 'size:8'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        // Find and validate invite code
        $inviteCode = InviteCode::where('code', strtoupper($validated['invite_code']))
            ->whereNull('used_by')
            ->first();

        if (! $inviteCode) {
            return response()->json([
                'message' => 'Invalid or already used invite code.',
                'errors' => [
                    'invite_code' => ['Invalid or already used invite code.'],
                ],
            ], 422);
        }

        // Create user and mark invite as used atomically
        $user = DB::transaction(function () use ($validated, $inviteCode) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $inviteCode->update([
                'used_by' => $user->id,
                'used_at' => now(),
            ]);

            return $user;
        });

        // Generate API token for mobile/API clients
        $deviceName = $validated['device_name'] ?? 'web';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Log in and return a token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'string', 'in:mobile,tablet,tv,car,desktop,other'],
        ]);

        $webGuard = Auth::guard('web');
        if (! $webGuard->attempt([
            'email' => $validated['email'],
            'password' => $validated['password'],
        ])) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
                'errors' => [
                    'email' => ['The provided credentials are incorrect.'],
                ],
            ], 422);
        }

        return $this->issueToken($request, $webGuard->user(), $validated['device_name'] ?? null, $validated['device_type'] ?? null);
    }

    /**
     * Start a passkey sign in. Any passkey of any user works (discoverable credentials), so no email is needed.
     */
    public function passkeyOptions(GenerateVerificationOptions $generate, PasskeyChallenges $challenges): JsonResponse
    {
        $options = $generate();

        return response()->json([
            'challenge_id' => $challenges->put($options, 'login'),
            'options' => WebAuthn::toBrowserArray($options),
        ]);
    }

    /**
     * Finish a passkey sign in: same answer and side effects as a password login
     */
    public function passkeyLogin(PasskeyLoginRequest $request, VerifyPasskey $verify, PasskeyChallenges $challenges): JsonResponse
    {
        $options = $challenges->pull($request->validated('challenge_id'), 'login', PublicKeyCredentialRequestOptions::class);
        if ($options === null) {
            return response()->json([
                'message' => 'The passkey challenge expired, try again.',
                'errors' => ['challenge_id' => ['The passkey challenge expired, try again.']],
            ], 422);
        }

        try {
            $passkey = $verify($request->credential(), $options);
        } catch (Throwable) {
            // Unknown, removed, or a signature that doesn't check out
            return response()->json(['message' => 'Passkey not recognized.'], 401);
        }

        return $this->issueToken($request, $passkey->user, $request->validated('device_name'), $request->validated('device_type'));
    }

    /**
     * A Sanctum token for the app, and the device row for it
     */
    private function issueToken(Request $request, User $user, ?string $deviceName, ?string $deviceType): JsonResponse
    {
        $token = $user->createToken($deviceName ?? 'web')->plainTextToken;

        // Capture the client-supplied device fingerprint so the device row gets
        // a real name ("Google Pixel 8") + type instead of the UA-parsed fallback
        // ("Android 35", "tablet").
        $clientId = $request->header('X-Client-ID');
        if ($clientId) {
            $user->getOrCreateDevice(
                clientId: $clientId,
                userAgent: $request->userAgent(),
                deviceName: $deviceName,
                deviceType: $deviceType,
            );
        }

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Log out (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Get the authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
            // So the app knows whether to suggest making a passkey
            'has_passkeys' => $request->user()->passkeys()->exists(),
        ]);
    }
}
