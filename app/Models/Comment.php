<?php

namespace App\Models;

use App\Services\EditionsDataService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::created(fn () => EditionsDataService::clearCache());
        static::deleted(fn () => EditionsDataService::clearCache());
        static::restored(fn () => EditionsDataService::clearCache());
    }

    protected $fillable = [
        'liveset_id',
        'user_id',
        'parent_id',
        'body',
        'timestamp_seconds',
    ];

    public function liveset(): BelongsTo
    {
        return $this->belongsTo(Liveset::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }
}
