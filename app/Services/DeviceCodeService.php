<?php

namespace App\Services;

use App\Models\DeviceCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeviceCodeService
{
    /**
     * Create a new device code for the given device.
     */
    public function createCode(string $deviceName, string $clientId): DeviceCode
    {
        return DeviceCode::create([
            'code' => DeviceCode::generateCode(),
            'client_id' => $clientId,
            'device_name' => $deviceName,
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    /**
     * Authorize a device code for a user, creating a Sanctum token.
     */
    public function authorize(DeviceCode $deviceCode, User $user): DeviceCode
    {
        return DB::transaction(function () use ($deviceCode, $user) {
            $deviceCode = DeviceCode::lockForUpdate()->find($deviceCode->id);

            if ($deviceCode->isExpired()) {
                abort(404, 'Device code has expired.');
            }

            if ($deviceCode->isAuthorized()) {
                abort(409, 'Device code has already been authorized.');
            }

            $token = $user->createToken($deviceCode->device_name);

            $deviceCode->update([
                'user_id' => $user->id,
                'token_id' => $token->accessToken->id,
                'encrypted_token' => $token->plainTextToken,
                'authorized_at' => now(),
            ]);

            return $deviceCode->fresh();
        });
    }

    /**
     * Poll a device code and return its status.
     *
     * @return array{status: string, token?: string, user?: array}
     */
    public function pollCode(string $code): array
    {
        $deviceCode = DeviceCode::where('code', strtoupper($code))->first();

        if (! $deviceCode || $deviceCode->isExpired()) {
            return ['status' => 'expired'];
        }

        if (! $deviceCode->isAuthorized()) {
            return ['status' => 'pending'];
        }

        $plainToken = $deviceCode->encrypted_token;

        if ($plainToken === null) {
            return ['status' => 'expired'];
        }

        $deviceCode->update(['encrypted_token' => null]);

        return [
            'status' => 'authorized',
            'token' => $plainToken,
            'user' => [
                'id' => $deviceCode->user->id,
                'name' => $deviceCode->user->name,
                'email' => $deviceCode->user->email,
            ],
        ];
    }
}
