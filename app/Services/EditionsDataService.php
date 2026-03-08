<?php

namespace App\Services;

use App\Exceptions\InvalidTimetableException;
use App\Models\Edition;
use App\Models\Liveset;
use App\Models\LivesetFile;
use App\Models\LivesetTrack;
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

        // Only modify if user has favorites
        if (empty($favoritedLivesetIds)) {
            return $baseEditions;
        }

        return array_map(function ($edition) use ($favoritedLivesetIds) {
            $edition['livesets'] = array_map(function ($liveset) use ($favoritedLivesetIds) {
                $liveset['is_favorited'] = in_array($liveset['id'], $favoritedLivesetIds);

                return $liveset;
            }, $edition['livesets']);

            return $edition;
        }, $baseEditions);
    }

    protected function fetchEditionsData(TimetablerService $timetablerService): array
    {
        return Edition::all()->map(function ($edition) use ($timetablerService) {
            return [
                ...$edition->toArray(),
                'date' => $edition->date?->format('j M Y'),
                'livesets' => $edition->livesets->sortBy('lineup_order')->map(function (Liveset $liveset) use ($timetablerService, $edition) {
                    return [
                        ...$liveset->toArray(),
                        'started_at' => $liveset->started_at?->format('j M Y H:i'),
                        'tracks' => $liveset->tracks->sortBy('order')->map(fn (LivesetTrack $track) => [
                            ...$track->toArray(),
                        ])->values()->toArray(),
                        'audio_waveform_url' => $liveset->audioWaveformUrl(),
                        'files' => $liveset->files->filter(fn (LivesetFile $file) => $file->existsOnDisk())->mapWithKeys(fn (LivesetFile $file) => [
                            $file->quality->value => \Storage::disk('public')->url($file->path),
                        ])->toArray() ?: null,
                        'timeslot' => $this->getTimeslotFor($timetablerService, $edition, $liveset),
                        'plays_count' => $liveset->plays_count,
                        'favorites_count' => $liveset->favorites_count,
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
