<?php

namespace App\Services;

use App\DTO\Timetabler\LivesetTimeslot;
use App\Exceptions\InvalidTimetableException;
use App\Models\Edition;
use Illuminate\Support\Collection;

class TimetablerService
{
    private array $timetables = [];

    public function __construct()
    {

    }

    /**
     * Resolve the timetable for the given edition (requires the edition to have timetabler mode on).
     *
     * @param \App\Models\Edition $edition
     *
     * @return \Illuminate\Support\Collection<int, \App\DTO\Timetabler\LivesetTimeslot> Liveset ID => LivesetTimeslot
     * @throws \App\Exceptions\InvalidTimetableException
     */
    public function getTimetable(Edition $edition): Collection
    {
        // Check if we have cached a result
        if (isset($this->timetables[$edition->id])) {
            $result = $this->timetables[$edition->id];

            if ($result instanceof InvalidTimetableException) {
                throw $result;
            } else {
                return $result;
            }
        }

        if (! $edition->timetabler_mode) {
            $this->cacheAndThrow($edition, new InvalidTimetableException('Timetabler mode is not enabled for this edition!'));
        }

        // Find the lowest configured start time for any liveset
        /** @var \Carbon\Carbon $lowestStartTime */
        $lowestStartTime = $edition->livesets->min('started_at');
        if (! $lowestStartTime) {
            $this->cacheAndThrow($edition, new InvalidTimetableException('Configure at least one liveset to have a start time!'));
        }

        // Check if any liveset is missing a duration and/or order number
        $livesetWithMissingData = $edition->livesets->whereNull('duration_in_seconds')->first() ?? $edition->livesets->whereNull('lineup_order')->first();
        if ($livesetWithMissingData) {
            $this->cacheAndThrow($edition, new InvalidTimetableException('Configure all livesets to have a duration and order number!'));
        }

        // Calculate the timetable
        $timetable = collect([]);
        $startTime = $lowestStartTime->toImmutable();
        $livesets = $edition->livesets->sortBy('lineup_order');
        foreach ($livesets as $liveset) {
            $endTime = $startTime->addSeconds($liveset->duration_in_seconds);
            $timetable->put($liveset->id, new LivesetTimeslot($liveset, $startTime, $endTime));
            $startTime = $endTime;
        }

        // Store in memory
        $this->timetables[$edition->id] = $timetable;

        return $timetable;
    }

    protected function cacheAndThrow(Edition $edition, InvalidTimetableException $exception): never
    {
        $this->timetables[$edition->id] = $exception;
        throw $exception;
    }

}
