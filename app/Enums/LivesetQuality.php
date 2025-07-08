<?php

namespace App\Enums;

enum LivesetQuality: string
{

    case LQ = 'lq';
    case HQ = 'hq';
    case LOSSLESS = 'lossless';


    public function short(): string
    {
        return match($this) {
            self::LOSSLESS => 'WAV',
            default => $this->label(),
        };
    }

    public function label(): string
    {
        return match($this) {
            self::LOSSLESS => 'Lossless',
            default => strtoupper( $this->value ),
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [$case->value => $case->label()])->toArray();
    }

}
