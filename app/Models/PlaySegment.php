<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaySegment extends Model
{
    protected $fillable = [
        'play_history_id',
        'start_position',
        'end_position',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function playHistory(): BelongsTo
    {
        return $this->belongsTo(PlayHistory::class);
    }

    /**
     * Get the duration of this segment in seconds.
     */
    public function getDurationAttribute(): ?int
    {
        if ($this->end_position === null) {
            return null;
        }

        return $this->end_position - $this->start_position;
    }

    /**
     * Check if this segment is currently active (not ended).
     */
    public function isActive(): bool
    {
        return $this->end_position === null;
    }
}
