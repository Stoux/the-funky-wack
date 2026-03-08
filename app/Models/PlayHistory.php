<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlayHistory extends Model
{
    protected $table = 'play_history';

    protected $appends = [
        'is_active',
        'effective_duration_listened',
    ];

    protected $fillable = [
        'user_id',
        'session_id',
        'client_id',
        'liveset_id',
        'started_at_position',
        'ended_at_position',
        'duration_listened',
        'quality',
        'platform',
        'counted_as_play',
        'disconnected_at',
        'stopped_at',
    ];

    protected function casts(): array
    {
        return [
            'counted_as_play' => 'boolean',
            'disconnected_at' => 'datetime',
            'stopped_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function liveset(): BelongsTo
    {
        return $this->belongsTo(Liveset::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(PlaySegment::class);
    }

    public function qualityChanges(): HasMany
    {
        return $this->hasMany(PlayQualityChange::class);
    }

    /**
     * Get the currently active segment for this play history.
     */
    public function getActiveSegmentAttribute(): ?PlaySegment
    {
        return $this->segments()
            ->whereNull('end_position')
            ->latest()
            ->first();
    }

    /**
     * Check if this play session is still active (recently updated, not disconnected or stopped).
     */
    public function getIsActiveAttribute(): bool
    {
        // Stopped or disconnected sessions are not active
        if ($this->stopped_at !== null || $this->disconnected_at !== null) {
            return false;
        }

        // Consider active if updated within last 60 seconds (sync interval is 15s)
        return $this->updated_at->diffInSeconds(now()) < 60;
    }

    /**
     * Get effective duration: stored value + time since last update if still active.
     */
    public function getEffectiveDurationListenedAttribute(): int
    {
        $duration = $this->duration_listened ?? 0;

        if ($this->is_active) {
            // Add time since last sync to compensate for delay
            $duration += (int) $this->updated_at->diffInSeconds(now());
        }

        return $duration;
    }
}
