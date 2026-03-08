<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Playlist extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'visibility',
        'share_code',
    ];

    protected $appends = ['slug'];

    protected static function booted(): void
    {
        static::creating(function (Playlist $playlist) {
            if (! $playlist->share_code) {
                $playlist->share_code = static::generateShareCode();
            }
        });
    }

    /**
     * Get URL-safe slug from playlist name.
     */
    public function getSlugAttribute(): string
    {
        return Str::slug($this->name) ?: 'playlist';
    }

    /**
     * The user who owns this playlist.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The playlist items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PlaylistItem::class)->orderBy('position');
    }

    /**
     * The livesets in this playlist.
     */
    public function livesets(): BelongsToMany
    {
        return $this->belongsToMany(Liveset::class, 'playlist_items')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /**
     * Check if the playlist is accessible publicly.
     */
    public function isPubliclyAccessible(): bool
    {
        return in_array($this->visibility, ['public', 'unlisted']);
    }

    /**
     * Generate a unique share code (8 characters).
     */
    public static function generateShareCode(): string
    {
        do {
            $code = Str::lower(Str::random(8));
        } while (static::withTrashed()->where('share_code', $code)->exists());

        return $code;
    }

    /**
     * Regenerate the share code (invalidates old URLs).
     */
    public function regenerateShareCode(): string
    {
        $this->update(['share_code' => static::generateShareCode()]);

        return $this->share_code;
    }
}
