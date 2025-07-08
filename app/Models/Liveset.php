<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Liveset extends Model
{
    use SoftDeletes;

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
            'started_at' =>  'datetime',
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
        if (!$this->audio_waveform_path) {
            return null;
        }

        $disk = Storage::disk('public');

        return $disk->exists($this->audio_waveform_path) ? $disk->url($this->audio_waveform_path) : null;
    }

}
