<?php

namespace App\Models;

use App\Services\EditionsDataService;
use Illuminate\Database\Eloquent\Model;

class LivesetTrack extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => EditionsDataService::clearCache());
        static::deleted(fn () => EditionsDataService::clearCache());
    }

    protected $fillable = [
        'liveset_id',
        'title',
        'timestamp',
        'order',
    ];

    public function liveset()
    {
        return $this->belongsTo(Liveset::class);
    }
}
