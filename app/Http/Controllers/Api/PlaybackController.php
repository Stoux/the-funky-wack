<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Listeners\PlaybackEventSubscriber;
use App\Models\Liveset;
use App\Models\PlaybackPosition;
use App\Models\PlayHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaybackController extends Controller
{
    public function __construct(
        protected PlaybackEventSubscriber $playbackEventSubscriber
    ) {}

    /**
     * Get paginated play history for the authenticated user.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentClientId = $request->header('X-Client-ID');

        // Pre-fetch all devices for this user
        $devices = $user->devices->keyBy('client_id');

        $history = $user
            ->playHistory()
            ->with('liveset:id,title,artist_name,edition_id')
            ->orderByDesc('created_at')
            ->paginate(20);

        // Transform to include device info
        $history->getCollection()->transform(function ($item) use ($devices, $currentClientId) {
            $device = $item->client_id ? $devices->get($item->client_id) : null;

            $item->device = $device ? [
                'display_name' => $device->display_name,
                'device_type' => $device->device_type,
                'is_current' => $device->client_id === $currentClientId,
            ] : null;

            return $item;
        });

        return response()->json($history);
    }

    /**
     * Record a play event (start, progress, seek, pause, resume, stop, quality).
     * Works for both authenticated and anonymous users.
     */
    public function recordPlay(Request $request): JsonResponse
    {
        \Log::info('[PlaybackController] recordPlay received', [
            'event' => $request->input('event'),
            'liveset_id' => $request->input('liveset_id'),
            'play_history_id' => $request->input('play_history_id'),
            'position' => $request->input('position'),
            'user_id' => $request->user()?->id,
        ]);

        $validated = $request->validate([
            'liveset_id' => ['required_if:event,start', 'integer', 'exists:livesets,id'],
            'event' => ['required', 'string', 'in:start,progress,seek,pause,resume,stop,quality'],
            'position' => ['required', 'integer', 'min:0'],
            'duration_listened' => ['nullable', 'integer', 'min:0'],
            'quality' => ['required_if:event,quality', 'nullable', 'string', 'max:10'],
            'platform' => ['nullable', 'string', 'max:20'],
            'play_history_id' => ['required_unless:event,start', 'integer', 'exists:play_history,id'],
            'from_position' => ['required_if:event,seek', 'integer', 'min:0'],
            'client_id' => ['nullable', 'string', 'max:64'],
        ]);

        $user = $request->user();
        $userId = $user?->id;
        $sessionId = $request->session()->getId();

        // Verify ownership for non-start events
        if ($validated['event'] !== 'start' && isset($validated['play_history_id'])) {
            $playHistory = PlayHistory::find($validated['play_history_id']);

            if (! $playHistory) {
                return response()->json([
                    'message' => 'Play history not found.',
                ], 404);
            }

            $isOwner = ($userId && $playHistory->user_id === $userId)
                || $playHistory->session_id === $sessionId;

            if (! $isOwner) {
                return response()->json([
                    'message' => 'Unauthorized.',
                ], 403);
            }
        }

        $response = match ($validated['event']) {
            'start' => $this->handleStart($validated, $sessionId, $userId),
            'progress' => $this->handleProgress($validated, $sessionId),
            'seek' => $this->handleSeek($validated, $sessionId),
            'pause' => $this->handlePause($validated, $sessionId),
            'resume' => $this->handleResume($validated, $sessionId),
            'stop' => $this->handleStop($validated, $sessionId),
            'quality' => $this->handleQualityChange($validated, $sessionId),
            default => ['message' => 'Invalid event.'],
        };

        return response()->json($response, $validated['event'] === 'start' ? 201 : 200);
    }

    protected function handleStart(array $validated, string $sessionId, ?int $userId): array
    {
        $playHistory = $this->playbackEventSubscriber->handleStart(
            sessionId: $sessionId,
            livesetId: $validated['liveset_id'],
            position: $validated['position'],
            quality: $validated['quality'] ?? null,
            clientId: $validated['client_id'] ?? null,
            userId: $userId,
            platform: $validated['platform'] ?? 'web'
        );

        return ['play_history_id' => $playHistory->id];
    }

    protected function handleProgress(array $validated, string $sessionId): array
    {
        return $this->playbackEventSubscriber->handleProgress(
            sessionId: $sessionId,
            playHistoryId: $validated['play_history_id'],
            position: $validated['position'],
            durationListened: $validated['duration_listened'] ?? 0
        );
    }

    protected function handleSeek(array $validated, string $sessionId): array
    {
        $this->playbackEventSubscriber->handleSeek(
            sessionId: $sessionId,
            playHistoryId: $validated['play_history_id'],
            fromPosition: $validated['from_position'],
            toPosition: $validated['position']
        );

        return ['status' => 'ok'];
    }

    protected function handlePause(array $validated, string $sessionId): array
    {
        $this->playbackEventSubscriber->handlePause(
            sessionId: $sessionId,
            playHistoryId: $validated['play_history_id'],
            position: $validated['position']
        );

        return ['status' => 'ok'];
    }

    protected function handleResume(array $validated, string $sessionId): array
    {
        $this->playbackEventSubscriber->handleResume(
            sessionId: $sessionId,
            playHistoryId: $validated['play_history_id'],
            position: $validated['position']
        );

        return ['status' => 'ok'];
    }

    protected function handleStop(array $validated, string $sessionId): array
    {
        $this->playbackEventSubscriber->handleStop(
            sessionId: $sessionId,
            playHistoryId: $validated['play_history_id'],
            position: $validated['position'],
            durationListened: $validated['duration_listened'] ?? 0
        );

        return ['status' => 'ok'];
    }

    protected function handleQualityChange(array $validated, string $sessionId): array
    {
        $this->playbackEventSubscriber->handleQualityChange(
            sessionId: $sessionId,
            playHistoryId: $validated['play_history_id'],
            position: $validated['position'],
            quality: $validated['quality']
        );

        return ['status' => 'ok'];
    }

    /**
     * Get all saved positions for the authenticated user.
     */
    public function positions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentClientId = $request->header('X-Client-ID');

        // Pre-fetch all devices for this user (avoids N+1)
        $devices = $user->devices->keyBy('client_id');

        $positions = $user
            ->playbackPositions()
            ->with('liveset:id,title,artist_name,edition_id,duration_in_seconds')
            ->get()
            ->map(function (PlaybackPosition $position) use ($devices, $currentClientId) {
                $device = $position->client_id ? $devices->get($position->client_id) : null;

                return [
                    'liveset_id' => $position->liveset_id,
                    'liveset' => $position->liveset,
                    'position' => $position->position,
                    'updated_at' => $position->updated_at->toIso8601String(),
                    'device' => $device ? [
                        'client_id' => $device->client_id,
                        'device_type' => $device->device_type,
                        'device_name' => $device->device_name,
                        'device_nickname' => $device->device_nickname,
                        'display_name' => $device->display_name,
                        'is_current' => $device->client_id === $currentClientId,
                    ] : null,
                ];
            });

        return response()->json([
            'positions' => $positions,
        ]);
    }

    /**
     * Save playback position for a liveset.
     */
    public function savePosition(Request $request, Liveset $liveset): JsonResponse
    {
        $validated = $request->validate([
            'position' => ['required', 'integer', 'min:0'],
        ]);

        $clientId = $request->header('X-Client-ID');

        // Register device if client_id provided
        if ($clientId) {
            $request->user()->getOrCreateDevice($clientId, $request->userAgent());
        }

        $request->user()->playbackPositions()->updateOrCreate(
            [
                'liveset_id' => $liveset->id,
                'client_id' => $clientId,
            ],
            ['position' => $validated['position']]
        );

        return response()->json([
            'message' => 'Position saved.',
        ]);
    }

    /**
     * Clear playback position for a liveset.
     */
    public function clearPosition(Request $request, Liveset $liveset): JsonResponse
    {
        $clientId = $request->header('X-Client-ID');

        $query = $request->user()
            ->playbackPositions()
            ->where('liveset_id', $liveset->id);

        // If client_id provided, only clear position for this device
        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        $query->delete();

        return response()->json([
            'message' => 'Position cleared.',
        ]);
    }
}
