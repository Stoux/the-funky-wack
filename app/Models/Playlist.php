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
     * Generate a unique share code.
     */
    public static function generateShareCode(): string
    {
        do {
            $code = Str::lower(Str::random(16));
        } while (static::where('share_code', $code)->exists());

        return $code;
    }

    /**
     * Get or generate the share code.
     */
    public function getOrCreateShareCode(): string
    {
        if (! $this->share_code) {
            $this->update(['share_code' => static::generateShareCode()]);
        }

        return $this->share_code;
    }
}
