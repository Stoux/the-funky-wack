<?php

namespace App\Models;

use App\Enums\TrackAliasStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An exact tracklist line and the track(s) it stands for. Several tracks for a mashup, none for an unknown.
 */
class TrackAlias extends Model
{
    /** @use HasFactory<\Database\Factories\TrackAliasFactory> */
    use HasFactory;

    protected $fillable = [
        'text',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => TrackAliasStatus::class,
        ];
    }

    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(Track::class, 'track_alias_track')->withPivot('position')->withTimestamps()->orderByPivot('position');
    }

    public function livesetTracks(): HasMany
    {
        return $this->hasMany(LivesetTrack::class, 'title', 'text');
    }
}
