<?php

namespace App\Models;

use App\Enums\LivesetQuality;
use App\Services\EditionsDataService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LivesetFile extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => EditionsDataService::clearCache());
        static::deleted(fn () => EditionsDataService::clearCache());
    }

    protected $fillable = [
        'liveset_id',
        'path',
        'quality',
        'original',
    ];

    protected function casts(): array
    {
        return [
            'original' => 'boolean',
            'quality' => LivesetQuality::class,
        ];
    }

    public function liveset(): BelongsTo
    {
        return $this->belongsTo(Liveset::class);
    }

    public function existsOnDisk(): bool
    {
        return \Storage::disk('public')->exists($this->path);
    }
}
