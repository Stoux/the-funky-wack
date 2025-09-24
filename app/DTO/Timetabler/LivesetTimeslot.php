<?php

namespace App\DTO\Timetabler;

use App\Models\Liveset;
use Carbon\CarbonInterface;

readonly class LivesetTimeslot
{
    public function __construct(
        public Liveset $liveset,
        public CarbonInterface $startTime,
        public CarbonInterface $endTime,
    ) {
    }

    public function timeslot(): string
    {
        return sprintf('%s - %s', $this->startTime->format('H:i'), $this->endTime->format('H:i'));
    }

}
