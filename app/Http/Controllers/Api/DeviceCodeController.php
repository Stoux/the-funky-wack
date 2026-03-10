<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceCodeRequest;
use App\Models\DeviceCode;
use App\Services\DeviceCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceCodeController extends Controller
{
    public function __construct(public DeviceCodeService $deviceCodeService) {}

    /**
     * Create a new device code.
     */
    public function store(StoreDeviceCodeRequest $request): JsonResponse
    {
        $deviceCode = $this->deviceCodeService->createCode(
            $request->validated('device_name'),
            $request->validated('client_id'),
        );

        return response()->json([
            'code' => $deviceCode->code,
            'expires_at' => $deviceCode->expires_at->toIso8601String(),
            'poll_interval' => 5,
        ], 201);
    }

    /**
     * Poll a device code for authorization status.
     */
    public function poll(string $code, Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => ['required', 'string', 'max:64'],
        ]);

        $result = $this->deviceCodeService->pollCode($code, $request->query('client_id'));

        $statusCode = match ($result['status']) {
            'pending' => 202,
            'authorized' => 200,
            default => 410,
        };

        return response()->json($result, $statusCode);
    }

    /**
     * Authorize a device code for the authenticated user.
     */
    public function authorize(string $code, Request $request): JsonResponse
    {
        $deviceCode = DeviceCode::where('code', strtoupper($code))
            ->where('expires_at', '>', now())
            ->first();

        if (! $deviceCode) {
            return response()->json(['message' => 'Device code not found or expired.'], 404);
        }

        if ($deviceCode->isAuthorized()) {
            return response()->json(['message' => 'Device code has already been authorized.'], 409);
        }

        $this->deviceCodeService->authorize($deviceCode, $request->user());

        return response()->json([
            'message' => 'Device authorized successfully.',
            'device_name' => $deviceCode->device_name,
        ]);
    }
}
