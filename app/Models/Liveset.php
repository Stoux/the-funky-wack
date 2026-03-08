<?php

namespace App\Models;

use App\Services\EditionsDataService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Liveset extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saved(fn () => EditionsDataService::clearCache());
        static::deleted(fn () => EditionsDataService::clearCache());
    }

    protected $fillable = [
        'edition_id',
        'title',
        'artist_name',
        'description',
        'bpm',
        'genre',
        'duration_in_seconds',
        'started_at',
        'lineup_order',
        'soundcloud_url',
        'audio_waveform_path',
    ];

    protected function casts()
    {
        return [
            'started_at' => 'datetime',
        ];
    }

    public function edition()
    {
        return $this->belongsTo(Edition::class);
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(LivesetTrack::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(LivesetFile::class);
    }

    public function audioWaveformUrl(): ?string
    {
        if (! $this->audio_waveform_path) {
            return null;
        }

        $disk = Storage::disk('public');

        return $disk->exists($this->audio_waveform_path) ? $disk->url($this->audio_waveform_path) : null;
    }

    /**
     * Play history for this liveset.
     */
    public function playHistory(): HasMany
    {
        return $this->hasMany(PlayHistory::class);
    }

    /**
     * Users who have favorited this liveset.
     */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')
            ->withTimestamps();
    }
}
