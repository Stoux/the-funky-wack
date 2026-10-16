<?php

namespace App\Services;

use App\Exceptions\InvalidTimetableException;
use App\Models\Comment;
use App\Models\Edition;
use App\Models\Liveset;
use App\Models\LivesetFile;
use App\Models\LivesetTrack;
use App\Models\Track;
use App\Models\TrackAlias;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class EditionsDataService
{
    public const CACHE_KEY = 'editions_data';

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function buildEditionsData(TimetablerService $timetablerService): array
    {
        // Cache the base editions data (without user-specific favorites)
        $baseEditions = Cache::remember(self::CACHE_KEY, 60, function () use ($timetablerService) {
            return $this->fetchEditionsData($timetablerService);
        });

        // Add user-specific favorites on top (fast - just array lookups)
        $favoritedLivesetIds = [];
        if (Auth::check()) {
            $favoritedLivesetIds = Auth::user()
                ->favoriteLivesets()
                ->pluck('liveset_id')
                ->toArray();
        }

        // Comment counts change all the time, one query here beats rebuilding the cache on every comment
        $commentCounts = Comment::query()
            ->selectRaw('liveset_id, count(*) as aggregate')
            ->groupBy('liveset_id')
            ->pluck('aggregate', 'liveset_id');

        return array_map(function ($edition) use ($favoritedLivesetIds, $commentCounts) {
            $edition['livesets'] = array_map(function ($liveset) use ($favoritedLivesetIds, $commentCounts) {
                $liveset['is_favorited'] = in_array($liveset['id'], $favoritedLivesetIds);
                $liveset['comments_count'] = (int) ($commentCounts[$liveset['id']] ?? 0);

                return $liveset;
            }, $edition['livesets']);

            return $edition;
        }, $baseEditions);
    }

    protected function fetchEditionsData(TimetablerService $timetablerService): array
    {
        $editions = Edition::with(['livesets.tracks', 'livesets.files'])->get();

        // The song(s) of every tracklist line, in one go
        $aliases = TrackAlias::forTexts($editions->flatMap->livesets->flatMap->tracks->pluck('title'));

        return $editions->map(function ($edition) use ($timetablerService, $aliases) {
            return [
                ...$edition->withoutRelations()->toArray(),
                'date' => $edition->date?->format('j M Y'),
                'livesets' => $edition->livesets->sortBy('lineup_order')->map(function (Liveset $liveset) use ($timetablerService, $edition, $aliases) {
                    return [
                        ...Arr::except($liveset->toArray(), ['tracks', 'files']),
                        'started_at' => $liveset->started_at?->format('j M Y H:i'),
                        'tracks' => $liveset->tracks->sortBy('order')->map(fn (LivesetTrack $track) => [
                            ...$track->toArray(),
                            // The song(s) this line stands for, several for a mashup
                            'songs' => $aliases->get(TrackAlias::lookupKey($track->title))?->tracks
                                ->map(fn (Track $song) => $song->toSummary())->values()->toArray() ?? [],
                        ])->values()->toArray(),
                        'audio_waveform_url' => $liveset->audioWaveformUrl(),
                        'files' => $liveset->files->filter(fn (LivesetFile $file) => $file->existsOnDisk())->mapWithKeys(fn (LivesetFile $file) => [
                            $file->quality->value => \Storage::disk('public')->url($file->path),
                        ])->toArray() ?: null,
                        'timeslot' => $this->getTimeslotFor($timetablerService, $edition, $liveset),
                        'plays_count' => $liveset->plays_count,
                        'favorites_count' => $liveset->favorites_count,
                        // Filled in per request, see buildEditionsData()
                        'comments_count' => 0,
                        'is_favorited' => false,
                    ];
                })->values()->toArray(),
            ];
        })->toArray();
    }

    protected function getTimeslotFor(TimetablerService $timetablerService, Edition $edition, Liveset $liveset): ?string
    {
        if (! $edition->timetabler_mode) {
            return null;
        }
        try {
            $timetable = $timetablerService->getTimetable($edition);

            return $timetable->get($liveset->id)->timeslot();
        } catch (InvalidTimetableException $e) {
            return null;
        }
    }
}
