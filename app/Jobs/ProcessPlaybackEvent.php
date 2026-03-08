<?php

namespace App\Jobs;

use App\Listeners\PlaybackEventSubscriber;
use App\Models\PlayHistory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessPlaybackEvent implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $sessionId,
        public string $eventName,
        public array $data
    ) {
        $this->onQueue('playback');
    }

    /**
     * Execute the job.
     */
    public function handle(PlaybackEventSubscriber $playbackEventSubscriber): void
    {
        Log::debug('ProcessPlaybackEvent: handling', [
            'session_id' => $this->sessionId,
            'event' => $this->eventName,
            'data' => $this->data,
        ]);

        // Get user ID: from play history if exists, or from session cache for start events
        $userId = null;
        if (isset($this->data['play_history_id'])) {
            $playHistory = PlayHistory::find($this->data['play_history_id']);
            $userId = $playHistory?->user_id;
        } elseif ($this->eventName === 'start') {
            // For start events, look up user from session cache (set during channel auth)
            $userId = Cache::get("playback_session_user:{$this->sessionId}");
        }

        match ($this->eventName) {
            'start' => $playbackEventSubscriber->handleStart(
                sessionId: $this->sessionId,
                livesetId: (int) $this->data['liveset_id'],
                position: (int) ($this->data['position'] ?? 0),
                quality: $this->data['quality'] ?? null,
                clientId: $this->data['client_id'] ?? null,
                userId: $userId,
                platform: $this->data['platform'] ?? 'web'
            ),
            'reconnect' => $playbackEventSubscriber->handleReconnect(
                sessionId: $this->sessionId
            ),
            'progress' => $playbackEventSubscriber->handleProgress(
                sessionId: $this->sessionId,
                playHistoryId: $this->requirePlayHistoryId(),
                position: (int) ($this->data['position'] ?? 0),
                durationListened: (int) ($this->data['duration_listened'] ?? 0)
            ),
            'seek' => $playbackEventSubscriber->handleSeek(
                sessionId: $this->sessionId,
                playHistoryId: $this->requirePlayHistoryId(),
                fromPosition: (int) ($this->data['from_position'] ?? 0),
                toPosition: (int) ($this->data['position'] ?? 0)
            ),
            'pause' => $playbackEventSubscriber->handlePause(
                sessionId: $this->sessionId,
                playHistoryId: $this->requirePlayHistoryId(),
                position: (int) ($this->data['position'] ?? 0)
            ),
            'resume' => $playbackEventSubscriber->handleResume(
                sessionId: $this->sessionId,
                playHistoryId: $this->requirePlayHistoryId(),
                position: (int) ($this->data['position'] ?? 0)
            ),
            'stop' => $playbackEventSubscriber->handleStop(
                sessionId: $this->sessionId,
                playHistoryId: $this->requirePlayHistoryId(),
                position: (int) ($this->data['position'] ?? 0),
                durationListened: (int) ($this->data['duration_listened'] ?? 0)
            ),
            'quality' => $playbackEventSubscriber->handleQualityChange(
                sessionId: $this->sessionId,
                playHistoryId: $this->requirePlayHistoryId(),
                position: (int) ($this->data['position'] ?? 0),
                quality: $this->data['quality'] ?? 'unknown'
            ),
            default => Log::warning('ProcessPlaybackEvent: unknown event', ['event' => $this->eventName]),
        };
    }

    /**
     * Get play_history_id from data or throw exception.
     */
    protected function requirePlayHistoryId(): int
    {
        if (! isset($this->data['play_history_id'])) {
            throw new \InvalidArgumentException(
                "ProcessPlaybackEvent: play_history_id required for '{$this->eventName}' event"
            );
        }

        return (int) $this->data['play_history_id'];
    }
}
