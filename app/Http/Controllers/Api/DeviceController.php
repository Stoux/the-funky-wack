<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    /**
     * List all devices for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $staleThreshold = now()->subDays(30);

        $devices = $request->user()
            ->devices()
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn (UserDevice $device) => [
                'id' => $device->id,
                'client_id' => $device->client_id,
                'device_type' => $device->device_type,
                'device_name' => $device->device_name,
                'device_nickname' => $device->device_nickname,
                'display_name' => $device->display_name,
                'is_hidden' => $device->is_hidden,
                'is_stale' => $device->last_seen_at->lt($staleThreshold),
                'last_seen_at' => $device->last_seen_at->toIso8601String(),
                'created_at' => $device->created_at->toIso8601String(),
            ]);

        return response()->json([
            'devices' => $devices,
        ]);
    }

    /**
     * Update device nickname.
     */
    public function update(Request $request, UserDevice $device): JsonResponse
    {
        // Verify ownership
        if ($device->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'device_nickname' => ['nullable', 'string', 'max:100'],
        ]);

        $device->update([
            'device_nickname' => $validated['device_nickname'],
        ]);

        return response()->json([
            'message' => 'Device updated.',
            'device' => [
                'id' => $device->id,
                'device_nickname' => $device->device_nickname,
                'display_name' => $device->display_name,
            ],
        ]);
    }

    /**
     * Hide device from continue listening options.
     */
    public function hide(Request $request, UserDevice $device): JsonResponse
    {
        // Verify ownership
        if ($device->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $device->update(['is_hidden' => true]);

        return response()->json([
            'message' => 'Device hidden.',
        ]);
    }

    /**
     * Show device in continue listening options.
     */
    public function show(Request $request, UserDevice $device): JsonResponse
    {
        // Verify ownership
        if ($device->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $device->update(['is_hidden' => false]);

        return response()->json([
            'message' => 'Device shown.',
        ]);
    }

    /**
     * Delete device permanently.
     */
    public function destroy(Request $request, UserDevice $device): JsonResponse
    {
        // Verify ownership
        if ($device->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $device->delete();

        return response()->json([
            'message' => 'Device deleted.',
        ]);
    }

    /**
     * Register or update the current device.
     * Called automatically on API requests with X-Client-ID header.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'string', 'max:64'],
        ]);

        $device = $request->user()->getOrCreateDevice(
            $validated['client_id'],
            $request->userAgent()
        );

        return response()->json([
            'device' => [
                'id' => $device->id,
                'client_id' => $device->client_id,
                'device_type' => $device->device_type,
                'device_name' => $device->device_name,
                'display_name' => $device->display_name,
                'is_current' => true,
            ],
        ]);
    }
}
