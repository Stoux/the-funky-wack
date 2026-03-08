<?php

namespace App\Services;

use App\Models\Liveset;
use App\Models\PlayHistory;
use App\Models\PlayQualityChange;
use App\Models\PlaySegment;
use Illuminate\Support\Facades\DB;

class PlayTrackingService
{
    /**
     * The percentage of the liveset that must be listened to count as a play.
     */
    public const PLAY_THRESHOLD_PERCENT = 5;

    /**
     * Minimum seconds required to count as a play (4 minutes).
     */
    public const PLAY_THRESHOLD_MIN_SECONDS = 240;

    /**
     * Maximum seconds required to count as a play (8 minutes).
     */
    public const PLAY_THRESHOLD_MAX_SECONDS = 480;

    /**
     * Start tracking a new play session.
     * Automatically closes any existing active sessions for this session_id.
     */
    public function startPlay(
        int $livesetId,
        ?int $userId,
        ?string $sessionId,
        int $position = 0,
        ?string $quality = null,
        string $platform = 'web',
        ?string $clientId = null
    ): PlayHistory {
        // Close any existing active sessions for this session_id
        // This handles cases where the client's stop event failed to send
        if ($sessionId) {
            $this->closeActiveSessions($sessionId);
        }

        return PlayHistory::create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'client_id' => $clientId,
            'liveset_id' => $livesetId,
            'started_at_position' => $position,
            'quality' => $quality,
            'platform' => $platform,
        ]);
    }

    /**
     * Close any active sessions for a given session_id.
     * Called when starting a new session to clean up stale ones.
     */
    protected function closeActiveSessions(string $sessionId): void
    {
        $activeSessions = PlayHistory::where('session_id', $sessionId)
            ->whereNull('ended_at_position')
            ->whereNull('disconnected_at')
            ->get();

        foreach ($activeSessions as $session) {
            // End any open segments
            $activeSegment = $session->active_segment;
            if ($activeSegment) {
                $activeSegment->update([
                    'end_position' => $activeSegment->start_position,
                    'ended_at' => now(),
                ]);
            }

            // Mark the session as ended
            $session->update([
                'ended_at_position' => $session->started_at_position,
            ]);
        }
    }

    /**
     * Update progress on an existing play session.
     *
     * @return bool Whether this update triggered a play count increment
     */
    public function updateProgress(
        PlayHistory $playHistory,
        int $currentPosition,
        int $durationListened
    ): bool {
        $liveset = $playHistory->liveset;

        // Server-side validation: duration can't exceed wall clock time since session started
        // Allow 10% tolerance for clock drift and network latency
        $maxAllowedDuration = (int) ceil($playHistory->created_at->diffInSeconds(now()) * 1.1);
        $validatedDuration = min($durationListened, $maxAllowedDuration);

        // Also cap at liveset duration if available
        if ($liveset->duration_in_seconds && $liveset->duration_in_seconds > 0) {
            $validatedDuration = min($validatedDuration, $liveset->duration_in_seconds);
        }

        $playHistory->update([
            'ended_at_position' => $currentPosition,
            'duration_listened' => $validatedDuration,
        ]);

        // Check if we should count this as a play
        if (! $playHistory->counted_as_play && $this->shouldCountAsPlay($playHistory, $liveset)) {
            return $this->markAsCountedPlay($playHistory, $liveset);
        }

        return false;
    }

    /**
     * End a play session.
     */
    public function endPlay(PlayHistory $playHistory, int $finalPosition, int $durationListened): void
    {
        $this->updateProgress($playHistory, $finalPosition, $durationListened);
    }

    /**
     * Check if a play session should be counted as a play.
     */
    protected function shouldCountAsPlay(PlayHistory $playHistory, Liveset $liveset): bool
    {
        if ($playHistory->counted_as_play) {
            return false;
        }

        $livesetDuration = $liveset->duration_in_seconds;
        if (! $livesetDuration || $livesetDuration <= 0) {
            return false;
        }

        // Calculate threshold: 5% of duration, clamped between 4-8 minutes
        $percentThreshold = ($livesetDuration * self::PLAY_THRESHOLD_PERCENT) / 100;
        $thresholdSeconds = max(
            self::PLAY_THRESHOLD_MIN_SECONDS,
            min(self::PLAY_THRESHOLD_MAX_SECONDS, $percentThreshold)
        );

        return $playHistory->duration_listened >= $thresholdSeconds;
    }

    /**
     * Mark a play as counted and increment the liveset's play count.
     */
    protected function markAsCountedPlay(PlayHistory $playHistory, Liveset $liveset): bool
    {
        return DB::transaction(function () use ($playHistory, $liveset) {
            $playHistory->update(['counted_as_play' => true]);
            $liveset->increment('plays_count');

            return true;
        });
    }

    /**
     * Check if this session already has a counted play for this liveset.
     * Used to prevent duplicate play counts within the same session.
     */
    public function hasCountedPlayInSession(?int $userId, ?string $sessionId, int $livesetId): bool
    {
        $query = PlayHistory::where('liveset_id', $livesetId)
            ->where('counted_as_play', true);

        if ($userId) {
            $query->where('user_id', $userId);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } else {
            return false;
        }

        return $query->exists();
    }

    /**
     * Pause a session due to WebSocket disconnect.
     * Sets disconnected_at and ended_at_position so the session can be resumed.
     */
    public function pauseSessionOnDisconnect(string $sessionId): void
    {
        // Find active session for this session ID (no ended_at_position or was disconnected)
        $playHistory = PlayHistory::where('session_id', $sessionId)
            ->where(function ($query) {
                $query->whereNull('ended_at_position')
                    ->orWhereNotNull('disconnected_at');
            })
            ->latest()
            ->first();

        if (! $playHistory) {
            return;
        }

        // End any active segment
        $lastPosition = $playHistory->ended_at_position ?? $playHistory->started_at_position;
        $this->endActiveSegment($playHistory, $lastPosition);

        // Mark as disconnected - use current position or last known position
        $playHistory->update([
            'disconnected_at' => now(),
            'ended_at_position' => $lastPosition,
        ]);
    }

    /**
     * Resume a session after WebSocket reconnect.
     * Clears disconnected_at and ended_at_position if session is resumable.
     */
    public function resumeSession(string $sessionId): ?PlayHistory
    {
        // Find a recently disconnected session (within 10 minutes)
        $playHistory = PlayHistory::where('session_id', $sessionId)
            ->whereNotNull('disconnected_at')
            ->where('disconnected_at', '>=', now()->subMinutes(30))
            ->latest()
            ->first();

        if (! $playHistory) {
            return null;
        }

        // Resume the session
        $playHistory->update([
            'disconnected_at' => null,
            'ended_at_position' => null,
        ]);

        return $playHistory;
    }

    /**
     * Get the active or resumable session for a session ID.
     */
    public function getActiveSession(string $sessionId): ?PlayHistory
    {
        return PlayHistory::where('session_id', $sessionId)
            ->where(function ($query) {
                // Active: no ended_at_position
                $query->whereNull('ended_at_position')
                    // Or disconnected but resumable (within 10 minutes)
                    ->orWhere(function ($q) {
                        $q->whereNotNull('disconnected_at')
                            ->where('disconnected_at', '>=', now()->subMinutes(30));
                    });
            })
            ->latest()
            ->first();
    }

    /**
     * Start a new segment for continuous playback tracking.
     */
    public function startSegment(PlayHistory $playHistory, int $position): PlaySegment
    {
        // End any currently active segment first
        $this->endActiveSegment($playHistory, $position);

        return PlaySegment::create([
            'play_history_id' => $playHistory->id,
            'start_position' => $position,
            'started_at' => now(),
        ]);
    }

    /**
     * End the currently active segment.
     */
    public function endSegment(PlayHistory $playHistory, int $position): void
    {
        $this->endActiveSegment($playHistory, $position);
    }

    /**
     * End any active segment for this play history.
     */
    protected function endActiveSegment(PlayHistory $playHistory, int $position): void
    {
        $activeSegment = $playHistory->active_segment;

        if ($activeSegment) {
            $activeSegment->update([
                'end_position' => $position,
                'ended_at' => now(),
            ]);
        }
    }

    /**
     * Record a seek event - ends current segment and starts a new one.
     */
    public function recordSeek(PlayHistory $playHistory, int $fromPosition, int $toPosition): void
    {
        // End current segment at the seek origin
        $this->endActiveSegment($playHistory, $fromPosition);

        // Start new segment at the seek destination
        $this->startSegment($playHistory, $toPosition);
    }

    /**
     * Record a quality change during playback.
     */
    public function recordQualityChange(PlayHistory $playHistory, int $position, string $quality): void
    {
        PlayQualityChange::create([
            'play_history_id' => $playHistory->id,
            'position' => $position,
            'quality' => $quality,
            'changed_at' => now(),
        ]);

        // Also update the current quality on the play history
        $playHistory->update(['quality' => $quality]);
    }

    /**
     * Get aggregated segment data for heatmap visualization.
     * Returns an array of position ranges with listen counts.
     */
    public function getSegmentHeatmap(int $livesetId, int $bucketSizeSeconds = 10): array
    {
        $segments = PlaySegment::whereHas('playHistory', function ($query) use ($livesetId) {
            $query->where('liveset_id', $livesetId);
        })
            ->whereNotNull('end_position')
            ->get();

        $heatmap = [];

        foreach ($segments as $segment) {
            $startBucket = (int) floor($segment->start_position / $bucketSizeSeconds);
            $endBucket = (int) floor($segment->end_position / $bucketSizeSeconds);

            for ($bucket = $startBucket; $bucket <= $endBucket; $bucket++) {
                $heatmap[$bucket] = ($heatmap[$bucket] ?? 0) + 1;
            }
        }

        return $heatmap;
    }
}
