<?php

namespace App\Models;

use App\Services\EditionsDataService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;

class Track extends Model
{
    /** @use HasFactory<\Database\Factories\TrackFactory> */
    use HasFactory, Searchable;

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

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'artist' => $this->artist,
            'title' => $this->title,
            'version' => $this->version,
        ];
    }

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
        return self::appearancesFor(collect([$this]))->get($this->id, collect());
    }

    /**
     * appearances() for many tracks in a few queries, keyed by track id (search results, liked tracks)
     *
     * @param  Collection<int, Track>  $tracks
     * @return Collection<int, Collection<int, array<string, mixed>>>
     */
    public static function appearancesFor(Collection $tracks): Collection
    {
        if ($tracks->isEmpty()) {
            return collect();
        }

        $tracks = \Illuminate\Database\Eloquent\Collection::make($tracks)->loadMissing('aliases');
        $texts = $tracks->flatMap->aliases->pluck('text')->unique()->values();

        // Grouped like MySQL matched them in the query, see TrackAlias::forTexts()
        $lines = $texts->isEmpty() ? collect() : LivesetTrack::query()
            ->whereIn('title', $texts)
            ->with('liveset.edition')
            ->get()
            ->filter(fn (LivesetTrack $line) => $line->liveset?->edition)
            ->groupBy(fn (LivesetTrack $line) => TrackAlias::lookupKey($line->title));

        return $tracks->mapWithKeys(fn (Track $track) => [$track->id => $track->aliases
            ->flatMap(fn (TrackAlias $alias) => $lines->get(TrackAlias::lookupKey($alias->text), collect()))
            ->unique('id')
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
            ->values(),
        ]);
    }

    /**
     * Same artists and title, another version (original, remixes, edits)
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Track>
     */
    public function otherVersions(): \Illuminate\Database\Eloquent\Collection
    {
        [$artists, $title] = explode('|', $this->match_key);

        return self::query()
            ->where('match_key', 'like', addcslashes("{$artists}|{$title}|", '%_\\').'%')
            ->whereKeyNot($this->id)
            ->orderBy('version')
            ->get();
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
