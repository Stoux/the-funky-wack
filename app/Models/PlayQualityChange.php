<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayQualityChange extends Model
{
    protected $fillable = [
        'play_history_id',
        'position',
        'quality',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function playHistory(): BelongsTo
    {
        return $this->belongsTo(PlayHistory::class);
    }
}
