<?php

namespace App\Models;

use App\Services\EditionsDataService;
use App\Services\TrackLinker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LivesetTrack extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => EditionsDataService::clearCache());
        static::created(fn (LivesetTrack $track) => app(TrackLinker::class)->link($track->title));
        static::deleted(fn () => EditionsDataService::clearCache());
    }

    protected $fillable = [
        'liveset_id',
        'title',
        'timestamp',
        'transition_start',
        'order',
    ];

    public function liveset()
    {
        return $this->belongsTo(Liveset::class);
    }

    /**
     * Linked by the exact line text, the rows themselves are recreated on every tracklist save
     */
    public function alias(): BelongsTo
    {
        return $this->belongsTo(TrackAlias::class, 'title', 'text');
    }
}
