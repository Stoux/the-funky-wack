<?php

namespace App\Services;

use App\Enums\TrackAliasStatus;
use App\Models\Track;
use App\Models\TrackAlias;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Links tracklist lines to canonical tracks. A line is parsed as "Artist - Title (Version)",
 * matched against existing tracks by a normalized key, and creates a new track when nothing matches.
 */
class TrackLinker
{
    /** Artists (or titles) that mean "we don't know this one" */
    private const UNKNOWN_NAMES = ['?', '??', '???', 'id', 'unknown'];

    /** Bracketed suffixes that don't make a different track */
    private const NOISE_PATTERN = '/^(?:(?:original|extended|radio|club|album|clean|dirty)(?: (?:mix|edit|version))?|unreleased|free download|(?:feat|ft)\.?\s.*)$/i';

    /** Separators between artists, so "A, B", "B & A" and "A x B" match */
    private const ARTIST_SEPARATOR_PATTERN = '/\s*(?:,|&|\+|\s(?:x|and|vs\.?|feat\.?|ft\.?)\s)\s*/i';

    /**
     * Make sure a tracklist line has an alias, linking or creating its track the first time it's seen.
     */
    public function link(string $text): TrackAlias
    {
        $alias = TrackAlias::firstOrCreate(['text' => $text], ['status' => TrackAliasStatus::Auto]);
        if (! $alias->wasRecentlyCreated) {
            return $alias;
        }

        $parsed = $this->parse($text);
        if ($parsed === null) {
            $alias->update(['status' => TrackAliasStatus::Unknown]);

            return $alias;
        }

        $alias->tracks()->attach($this->findOrCreateTrack(...$parsed)->id, ['position' => 0]);

        return $alias;
    }

    /**
     * @return array{artist: string, title: string, version: ?string}|null null for an unknown track
     */
    public function parse(string $text): ?array
    {
        $parts = preg_split('/\s+[-–—]\s+/u', trim($text), 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$artist, $title] = array_map('trim', $parts);
        $versions = [];

        // Peel bracketed suffixes off the end: "Title (Overmono Remix) [Unreleased]"
        while (preg_match('/^(.*?)\s*[(\[]([^()\[\]]+)[)\]]$/u', $title, $matches) && $matches[1] !== '') {
            $title = $matches[1];
            if (! preg_match(self::NOISE_PATTERN, trim($matches[2]))) {
                array_unshift($versions, trim($matches[2]));
            }
        }

        if ($this->isUnknown($artist) || $this->isUnknown($title)) {
            return null;
        }

        return [
            'artist' => $artist,
            'title' => $title,
            'version' => $versions ? implode(', ', $versions) : null,
        ];
    }

    /**
     * Case, accents, punctuation and artist order don't matter
     */
    public function matchKey(string $artist, string $title, ?string $version): string
    {
        $artists = collect(preg_split(self::ARTIST_SEPARATOR_PATTERN, $artist))
            ->map(fn (string $name) => $this->normalize($name))
            ->filter()
            ->sort()
            ->implode('+');

        return Str::limit($artists.'|'.$this->normalize($title).'|'.$this->normalize($version ?? ''), 255, '');
    }

    public function findOrCreateTrack(string $artist, string $title, ?string $version): Track
    {
        $matchKey = $this->matchKey($artist, $title, $version);

        return Track::firstWhere('match_key', $matchKey) ?? Track::create([
            'artist' => $artist,
            'title' => $title,
            'version' => $version,
            'slug' => $this->uniqueSlug($artist, $title, $version),
            'match_key' => $matchKey,
        ]);
    }

    /**
     * Point a line at other track(s), in order. Several tracks make a mashup.
     *
     * @param  array<int, int>  $trackIds
     */
    public function relink(TrackAlias $alias, array $trackIds): void
    {
        $previousTrackIds = $alias->tracks()->pluck('tracks.id')->all();

        $alias->tracks()->sync(collect(array_values(array_unique($trackIds)))
            ->mapWithKeys(fn (int $trackId, int $position) => [$trackId => ['position' => $position]])
            ->all());
        $alias->update(['status' => TrackAliasStatus::Reviewed]);

        $this->pruneOrphans($previousTrackIds);
    }

    /**
     * For lines written as "Title - Artist"
     */
    public function swap(TrackAlias $alias): void
    {
        $parts = preg_split('/\s+[-–—]\s+/u', trim($alias->text), 2);
        if (count($parts) !== 2) {
            return;
        }

        $parsed = $this->parse("{$parts[1]} - {$parts[0]}");
        if ($parsed === null) {
            $this->markUnknown($alias);

            return;
        }

        $this->relink($alias, [$this->findOrCreateTrack(...$parsed)->id]);
    }

    public function markUnknown(TrackAlias $alias): void
    {
        $this->relink($alias, []);
    }

    public function review(TrackAlias $alias): void
    {
        $alias->update(['status' => TrackAliasStatus::Reviewed]);
    }

    /**
     * Move every line of $source over to $target and drop $source
     */
    public function merge(Track $source, Track $target): void
    {
        if ($source->is($target)) {
            return;
        }

        DB::transaction(function () use ($source, $target) {
            foreach ($source->aliases as $alias) {
                $trackIds = $alias->tracks->pluck('id')
                    ->map(fn (int $trackId) => $trackId === $source->id ? $target->id : $trackId)
                    ->all();
                $alias->tracks()->sync(collect(array_values(array_unique($trackIds)))
                    ->mapWithKeys(fn (int $trackId, int $position) => [$trackId => ['position' => $position]])
                    ->all());
            }

            $source->delete();
        });
    }

    /**
     * Tracks that no line points at anymore
     *
     * @param  array<int, int>  $trackIds
     */
    private function pruneOrphans(array $trackIds): void
    {
        Track::query()
            ->whereIn('id', $trackIds)
            ->whereDoesntHave('aliases')
            ->delete();
    }

    private function isUnknown(string $name): bool
    {
        return in_array(Str::lower(trim($name)), self::UNKNOWN_NAMES, true);
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii($value)));
    }

    private function uniqueSlug(string $artist, string $title, ?string $version): string
    {
        $base = Str::limit(Str::slug(implode(' ', array_filter([$artist, $title, $version]))), 200, '') ?: 'track';
        $slug = $base;
        $suffix = 2;

        while (Track::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
