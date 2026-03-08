<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Listeners\PlaybackEventSubscriber;
use App\Models\PlayHistory;
use App\Services\PlayTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReverbWebhookController extends Controller
{
    public function __construct(
        protected PlayTrackingService $playTrackingService,
        protected PlaybackEventSubscriber $playbackEventSubscriber
    ) {}

    /**
     * Handle incoming Reverb webhook events.
     */
    public function handle(Request $request): JsonResponse
    {
        // Verify webhook secret
        $secret = config('reverb.apps.apps.0.webhooks.0.headers.X-Reverb-Secret');
        if ($secret && $request->header('X-Reverb-Secret') !== $secret) {
            Log::warning('Reverb webhook: invalid secret');

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $event = $request->input('event');
        $channel = $request->input('channel');

        Log::debug('Reverb webhook received', [
            'event' => $event,
            'channel' => $channel,
        ]);

        // Only handle playback channel events
        if (! str_starts_with($channel, 'presence-playback.')) {
            return response()->json(['status' => 'ignored']);
        }

        // Extract session ID from channel name (presence-playback.{sessionId})
        $sessionId = str_replace('presence-playback.', '', $channel);

        match ($event) {
            'member_added' => $this->handleMemberAdded($sessionId, $request->input('user_info', [])),
            'member_removed' => $this->handleMemberRemoved($sessionId, $request->input('user_info', [])),
            'client_event' => $this->handleClientEvent($sessionId, $request->input('name'), $request->input('data', [])),
            default => null,
        };

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle member joining the playback channel (connection established/restored).
     */
    protected function handleMemberAdded(string $sessionId, array $userInfo): void
    {
        Log::debug('Playback channel: member added', [
            'session_id' => $sessionId,
            'user_info' => $userInfo,
        ]);

        // Try to resume session, this will broadcast appropriate events
        $this->playbackEventSubscriber->handleReconnect($sessionId);
    }

    /**
     * Handle member leaving the playback channel (connection dropped).
     */
    protected function handleMemberRemoved(string $sessionId, array $userInfo): void
    {
        Log::debug('Playback channel: member removed', [
            'session_id' => $sessionId,
            'user_info' => $userInfo,
        ]);

        // Pause the active session - set ended_at_position to current position
        $this->playTrackingService->pauseSessionOnDisconnect($sessionId);
    }

    /**
     * Handle client events (whispers) for playback tracking.
     * Events: client-start, client-progress, client-seek, client-pause, client-resume, client-stop
     */
    protected function handleClientEvent(string $sessionId, string $eventName, array $data): void
    {
        Log::debug('Playback client event', [
            'session_id' => $sessionId,
            'event' => $eventName,
            'data' => $data,
        ]);

        // Client events are prefixed with "client-"
        $action = str_replace('client-', '', $eventName);

        // Get user ID from the session's active play history if exists
        $userId = null;
        if (isset($data['play_history_id'])) {
            $playHistory = PlayHistory::find($data['play_history_id']);
            $userId = $playHistory?->user_id;
        }

        match ($action) {
            'start' => $this->handleStart($sessionId, $data, $userId),
            'progress' => $this->handleProgress($sessionId, $data),
            'seek' => $this->handleSeek($sessionId, $data),
            'pause' => $this->handlePause($sessionId, $data),
            'resume' => $this->handleResume($sessionId, $data),
            'stop' => $this->handleStop($sessionId, $data),
            'quality' => $this->handleQuality($sessionId, $data),
            default => Log::warning('Unknown client event', ['action' => $action]),
        };
    }

    protected function handleStart(string $sessionId, array $data, ?int $userId): void
    {
        $playHistory = $this->playbackEventSubscriber->handleStart(
            sessionId: $sessionId,
            livesetId: $data['liveset_id'],
            position: $data['position'] ?? 0,
            quality: $data['quality'] ?? null,
            clientId: $data['client_id'] ?? null,
            userId: $userId,
            platform: $data['platform'] ?? 'web'
        );

        // The play_history_id is sent back via the presence channel
        // Client should listen for session.started event
        Log::debug('Playback started via WebSocket', [
            'session_id' => $sessionId,
            'play_history_id' => $playHistory->id,
        ]);
    }

    protected function handleProgress(string $sessionId, array $data): void
    {
        if (! isset($data['play_history_id'])) {
            return;
        }

        $this->playbackEventSubscriber->handleProgress(
            sessionId: $sessionId,
            playHistoryId: $data['play_history_id'],
            position: $data['position'] ?? 0,
            durationListened: $data['duration_listened'] ?? 0
        );
    }

    protected function handleSeek(string $sessionId, array $data): void
    {
        if (! isset($data['play_history_id'])) {
            return;
        }

        $this->playbackEventSubscriber->handleSeek(
            sessionId: $sessionId,
            playHistoryId: $data['play_history_id'],
            fromPosition: $data['from_position'] ?? 0,
            toPosition: $data['position'] ?? 0
        );
    }

    protected function handlePause(string $sessionId, array $data): void
    {
        if (! isset($data['play_history_id'])) {
            return;
        }

        $this->playbackEventSubscriber->handlePause(
            sessionId: $sessionId,
            playHistoryId: $data['play_history_id'],
            position: $data['position'] ?? 0
        );
    }

    protected function handleResume(string $sessionId, array $data): void
    {
        if (! isset($data['play_history_id'])) {
            return;
        }

        $this->playbackEventSubscriber->handleResume(
            sessionId: $sessionId,
            playHistoryId: $data['play_history_id'],
            position: $data['position'] ?? 0
        );
    }

    protected function handleStop(string $sessionId, array $data): void
    {
        if (! isset($data['play_history_id'])) {
            return;
        }

        $this->playbackEventSubscriber->handleStop(
            sessionId: $sessionId,
            playHistoryId: $data['play_history_id'],
            position: $data['position'] ?? 0,
            durationListened: $data['duration_listened'] ?? 0
        );
    }

    protected function handleQuality(string $sessionId, array $data): void
    {
        if (! isset($data['play_history_id'])) {
            return;
        }

        $this->playbackEventSubscriber->handleQualityChange(
            sessionId: $sessionId,
            playHistoryId: $data['play_history_id'],
            position: $data['position'] ?? 0,
            quality: $data['quality'] ?? 'unknown'
        );
    }
}
