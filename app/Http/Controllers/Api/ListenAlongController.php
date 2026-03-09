<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ListenAlongService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListenAlongController extends Controller
{
    public function __construct(
        protected ListenAlongService $listenAlongService
    ) {}

    /**
     * List all active listen rooms.
     */
    public function sessions(Request $request): JsonResponse
    {
        $user = $request->user();

        $sessions = $this->listenAlongService->getActiveSessions(
            viewerUserId: $user?->id,
            viewerAuthenticated: $user !== null
        );

        return response()->json(['sessions' => $sessions]);
    }

    /**
     * Get the current state of a room (for joining listeners).
     */
    public function state(Request $request, string $channelToken): JsonResponse
    {
        $user = $request->user();

        $sessions = $this->listenAlongService->getActiveSessions(
            viewerUserId: $user?->id,
            viewerAuthenticated: $user !== null
        );

        $session = collect($sessions)->firstWhere('channel_token', $channelToken);

        if (! $session) {
            return response()->json(['message' => 'Room not found or no longer active.'], 404);
        }

        return response()->json($session);
    }

    /**
     * Join a listen room.
     */
    public function join(Request $request, string $channelToken): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:synced,independent'],
        ]);

        $clientId = $request->header('X-Client-ID');
        if (! $clientId) {
            return response()->json(['message' => 'X-Client-ID header is required.'], 422);
        }

        $user = $request->user();

        $member = $this->listenAlongService->joinRoom(
            channelToken: $channelToken,
            clientId: $clientId,
            userId: $user?->id,
            mode: $validated['mode']
        );

        if (! $member) {
            return response()->json(['message' => 'Room not found or no longer active.'], 404);
        }

        return response()->json([
            'member_id' => $member->id,
            'mode' => $member->mode,
        ]);
    }

    /**
     * Leave a listen room.
     */
    public function leave(Request $request, string $channelToken): JsonResponse
    {
        $clientId = $request->header('X-Client-ID') ?? $request->input('client_id');
        if (! $clientId) {
            return response()->json(['message' => 'X-Client-ID header is required.'], 422);
        }

        $this->listenAlongService->leaveRoom(
            channelToken: $channelToken,
            clientId: $clientId
        );

        return response()->json(['message' => 'Left room.']);
    }

    /**
     * Detach from synced mode to independent.
     */
    public function detach(Request $request, string $channelToken): JsonResponse
    {
        $clientId = $request->header('X-Client-ID');
        if (! $clientId) {
            return response()->json(['message' => 'X-Client-ID header is required.'], 422);
        }

        $newRoom = $this->listenAlongService->detachListener(
            channelToken: $channelToken,
            clientId: $clientId
        );

        return response()->json([
            'message' => 'Detached to independent mode.',
            'new_room_token' => $newRoom?->channel_token,
        ]);
    }

    /**
     * Pause sync (listener paused their playback).
     */
    public function pauseSync(Request $request, string $channelToken): JsonResponse
    {
        $clientId = $request->header('X-Client-ID');
        if (! $clientId) {
            return response()->json(['message' => 'X-Client-ID header is required.'], 422);
        }

        $this->listenAlongService->pauseSync(
            channelToken: $channelToken,
            clientId: $clientId
        );

        return response()->json(['message' => 'Sync paused.']);
    }

    /**
     * Resume sync (listener resumed playback and chose to resync).
     */
    public function resumeSync(Request $request, string $channelToken): JsonResponse
    {
        $clientId = $request->header('X-Client-ID');
        if (! $clientId) {
            return response()->json(['message' => 'X-Client-ID header is required.'], 422);
        }

        $this->listenAlongService->resumeSync(
            channelToken: $channelToken,
            clientId: $clientId
        );

        return response()->json(['message' => 'Sync resumed.']);
    }
}
