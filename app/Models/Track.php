<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Track extends Model
{
    /** @use HasFactory<\Database\Factories\TrackFactory> */
    use HasFactory;

    protected $fillable = [
        'artist',
        'title',
        'version',
        'slug',
        'match_key',
    ];

    public function aliases(): BelongsToMany
    {
        return $this->belongsToMany(TrackAlias::class, 'track_alias_track')->withPivot('position')->withTimestamps();
    }

    /**
     * "Artist - Title (Version)"
     */
    public function displayName(): string
    {
        $name = "{$this->artist} - {$this->title}";

        return $this->version ? "{$name} ({$this->version})" : $name;
    }
}
