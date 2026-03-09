<?php

namespace App\Listeners;

use App\Events\PlaybackCountedAsPlay;
use App\Events\PlaybackSessionExpired;
use App\Events\PlaybackSessionStarted;
use App\Models\PlayHistory;
use App\Services\ListenAlongService;
use App\Services\PlayTrackingService;
use Illuminate\Support\Facades\Log;

class PlaybackEventSubscriber
{
    public function __construct(
        protected PlayTrackingService $playTrackingService,
        protected ListenAlongService $listenAlongService
    ) {}

    /**
     * Handle playback start event.
     */
    public function handleStart(
        string $sessionId,
        int $livesetId,
        int $position,
        ?string $quality,
        ?string $clientId,
        ?int $userId = null,
        string $platform = 'web'
    ): PlayHistory {
        $playHistory = $this->playTrackingService->startPlay(
            livesetId: $livesetId,
            userId: $userId,
            sessionId: $sessionId,
            position: $position,
            quality: $quality,
            platform: $platform,
            clientId: $clientId
        );

        // Start the first segment for this session
        $this->playTrackingService->startSegment($playHistory, $position);

        // Record initial quality
        if ($quality) {
            $this->playTrackingService->recordQualityChange($playHistory, $position, $quality);
        }

        // Broadcast session started to all connected clients
        event(new PlaybackSessionStarted($playHistory, $sessionId));

        // Listen Along: create room or update existing room's track
        $existingRoom = $this->listenAlongService->findActiveRoomForSession($sessionId);
        if ($existingRoom) {
            $this->listenAlongService->updateHostPlayHistory($existingRoom, $playHistory);
        } else {
            $this->listenAlongService->createRoom($playHistory);
        }

        Log::debug('Playback: session started', [
            'session_id' => $sessionId,
            'play_history_id' => $playHistory->id,
            'liveset_id' => $livesetId,
        ]);

        return $playHistory;
    }

    /**
     * Handle playback progress event.
     */
    public function handleProgress(
        string $sessionId,
        int $playHistoryId,
        int $position,
        int $durationListened
    ): array {
        $playHistory = PlayHistory::find($playHistoryId);

        if (! $playHistory) {
            Log::warning('Playback: progress for unknown play_history', [
                'play_history_id' => $playHistoryId,
            ]);

            return ['counted_as_play' => false];
        }

        $countedAsPlay = $this->playTrackingService->updateProgress(
            $playHistory,
            $position,
            $durationListened
        );

        // If this update triggered a play count, broadcast it
        if ($countedAsPlay) {
            event(new PlaybackCountedAsPlay($playHistory->fresh(), $sessionId));

            Log::debug('Playback: counted as play', [
                'session_id' => $sessionId,
                'play_history_id' => $playHistoryId,
            ]);
        }

        return ['counted_as_play' => $playHistory->fresh()->counted_as_play];
    }

    /**
     * Handle playback seek event.
     */
    public function handleSeek(
        string $sessionId,
        int $playHistoryId,
        int $fromPosition,
        int $toPosition
    ): void {
        $playHistory = PlayHistory::find($playHistoryId);

        if (! $playHistory) {
            return;
        }

        // Record the seek - ends current segment and starts new one
        $this->playTrackingService->recordSeek($playHistory, $fromPosition, $toPosition);

        // Listen Along: broadcast seek to synced listeners + update Live page
        $room = $this->listenAlongService->findActiveRoomForSession($sessionId);
        if ($room) {
            $this->listenAlongService->broadcastHostAction($room, 'host.seek', [
                'position' => $toPosition,
            ]);

            event(new \App\Events\LiveSessionsUpdated);
        }

        Log::debug('Playback: seek detected', [
            'session_id' => $sessionId,
            'play_history_id' => $playHistoryId,
            'from_position' => $fromPosition,
            'to_position' => $toPosition,
        ]);
    }

    /**
     * Handle playback pause event.
     */
    public function handlePause(
        string $sessionId,
        int $playHistoryId,
        int $position
    ): void {
        $playHistory = PlayHistory::find($playHistoryId);

        if ($playHistory) {
            // End the current segment on pause
            $this->playTrackingService->endSegment($playHistory, $position);
        }

        // Listen Along: broadcast pause to synced listeners
        $room = $this->listenAlongService->findActiveRoomForSession($sessionId);
        if ($room) {
            $this->listenAlongService->broadcastHostAction($room, 'host.pause', [
                'position' => $position,
            ]);
        }

        Log::debug('Playback: paused', [
            'session_id' => $sessionId,
            'play_history_id' => $playHistoryId,
            'position' => $position,
        ]);
    }

    /**
     * Handle playback resume event.
     */
    public function handleResume(
        string $sessionId,
        int $playHistoryId,
        int $position
    ): void {
        $playHistory = PlayHistory::find($playHistoryId);

        if ($playHistory) {
            // Start a new segment on resume
            $this->playTrackingService->startSegment($playHistory, $position);
        }

        // Listen Along: broadcast resume to synced listeners
        $room = $this->listenAlongService->findActiveRoomForSession($sessionId);
        if ($room) {
            $this->listenAlongService->broadcastHostAction($room, 'host.resume', [
                'position' => $position,
            ]);
        }

        Log::debug('Playback: resumed', [
            'session_id' => $sessionId,
            'play_history_id' => $playHistoryId,
            'position' => $position,
        ]);
    }

    /**
     * Handle playback stop event.
     */
    public function handleStop(
        string $sessionId,
        int $playHistoryId,
        int $position,
        int $durationListened
    ): void {
        $playHistory = PlayHistory::find($playHistoryId);

        if (! $playHistory) {
            return;
        }

        // End the final segment
        $this->playTrackingService->endSegment($playHistory, $position);

        // End the play session
        $this->playTrackingService->endPlay(
            $playHistory,
            $position,
            $durationListened
        );

        // Listen Along: end the room when host stops
        $room = $this->listenAlongService->findActiveRoomForSession($sessionId);
        if ($room) {
            $this->listenAlongService->endRoom($room);
        }

        Log::debug('Playback: stopped', [
            'session_id' => $sessionId,
            'play_history_id' => $playHistoryId,
            'position' => $position,
            'duration_listened' => $durationListened,
        ]);
    }

    /**
     * Handle quality change event.
     */
    public function handleQualityChange(
        string $sessionId,
        int $playHistoryId,
        int $position,
        string $quality
    ): void {
        $playHistory = PlayHistory::find($playHistoryId);

        if (! $playHistory) {
            return;
        }

        // Record the quality change with position and timestamp
        $this->playTrackingService->recordQualityChange($playHistory, $position, $quality);

        Log::debug('Playback: quality changed', [
            'session_id' => $sessionId,
            'play_history_id' => $playHistoryId,
            'position' => $position,
            'quality' => $quality,
        ]);
    }

    /**
     * Handle session expiry check on reconnect.
     */
    public function handleReconnect(string $sessionId): ?PlayHistory
    {
        // Check if there's a resumable session
        $playHistory = $this->playTrackingService->resumeSession($sessionId);

        if (! $playHistory) {
            // Check if there's an expired session
            $expiredSession = PlayHistory::where('session_id', $sessionId)
                ->whereNotNull('disconnected_at')
                ->where('disconnected_at', '<', now()->subMinutes(30))
                ->latest()
                ->first();

            if ($expiredSession) {
                event(new PlaybackSessionExpired(
                    $sessionId,
                    $expiredSession->id,
                    $expiredSession->liveset_id
                ));
            }

            return null;
        }

        // Session was resumed - start a new segment from the last position
        $resumePosition = $playHistory->ended_at_position ?? $playHistory->started_at_position;
        $this->playTrackingService->startSegment($playHistory, $resumePosition);

        // Broadcast the session started event again
        event(new PlaybackSessionStarted($playHistory, $sessionId));

        return $playHistory;
    }
}
