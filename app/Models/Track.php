<?php

namespace App\Models;

use App\Services\EditionsDataService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class Track extends Model
{
    /** @use HasFactory<\Database\Factories\TrackFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(fn () => EditionsDataService::clearCache());
        static::deleted(fn () => EditionsDataService::clearCache());
    }

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

    public function likedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'track_likes')->withTimestamps();
    }

    /**
     * Every tracklist line this track was played as, newest edition first, in playing order within a set
     *
     * @return Collection<int, array{liveset_id: int, liveset_title: string, artist_name: string, edition_number: string, edition_date: ?string, timestamp: ?int, line: string}>
     */
    public function appearances(): Collection
    {
        return LivesetTrack::query()
            ->whereIn('title', $this->aliases()->select('text'))
            ->with('liveset.edition')
            ->get()
            ->filter(fn (LivesetTrack $line) => $line->liveset?->edition)
            // Sorts are stable, so this keeps the playing order within a set
            ->sortBy('timestamp')
            ->sortByDesc(fn (LivesetTrack $line) => $line->liveset->edition->date?->getTimestamp() ?? 0)
            ->map(fn (LivesetTrack $line) => [
                'liveset_id' => $line->liveset->id,
                'liveset_title' => $line->liveset->title,
                'artist_name' => $line->liveset->artist_name,
                'edition_number' => $line->liveset->edition->number,
                'edition_date' => $line->liveset->edition->date?->format('j M Y'),
                'timestamp' => $line->timestamp,
                'line' => $line->title,
            ])
            ->values();
    }

    /**
     * @return array{slug: string, artist: string, title: string, version: ?string}
     */
    public function toSummary(): array
    {
        return [
            'slug' => $this->slug,
            'artist' => $this->artist,
            'title' => $this->title,
            'version' => $this->version,
        ];
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
