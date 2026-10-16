<?php

namespace App\Models;

use App\Enums\TrackAliasStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

    /**
     * The aliases (with their tracks) for a set of tracklist lines, keyed by lookupKey().
     * Use this instead of eager loading LivesetTrack::alias(): MySQL compares the text case and
     * accent insensitively (so "artist - title" uses the "Artist - Title" alias), Eloquent's
     * eager load matching in PHP is exact and would miss it.
     *
     * @param  iterable<string>  $texts
     * @return Collection<string, TrackAlias>
     */
    public static function forTexts(iterable $texts): Collection
    {
        $texts = collect($texts)->unique()->values();
        if ($texts->isEmpty()) {
            return collect();
        }

        return static::query()
            ->whereIn('text', $texts)
            ->with('tracks')
            ->get()
            ->keyBy(fn (TrackAlias $alias) => self::lookupKey($alias->text));
    }

    /**
     * The sets each alias is used in, keyed by lookupKey(). Matched like MySQL does, see forTexts().
     *
     * @param  Collection<int, TrackAlias>  $aliases
     * @return Collection<string, Collection<int, Liveset>>
     */
    public static function livesetsFor(Collection $aliases): Collection
    {
        if ($aliases->isEmpty()) {
            return collect();
        }

        return LivesetTrack::query()
            ->whereIn('title', $aliases->pluck('text')->unique()->values())
            ->with('liveset:id,title')
            ->get()
            ->filter(fn (LivesetTrack $line) => $line->liveset)
            ->groupBy(fn (LivesetTrack $line) => self::lookupKey($line->title))
            ->map(fn (Collection $lines) => $lines->pluck('liveset')->unique('id')->values());
    }

    /**
     * Roughly how MySQL's utf8mb4_unicode_ci sees a line: no case, accents or surrounding spaces
     */
    public static function lookupKey(string $text): string
    {
        return Str::lower(Str::ascii(trim($text)));
    }
}
