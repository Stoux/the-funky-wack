<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ListenRoom extends Model
{
    protected $fillable = [
        'channel_token',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * All members of this listen room.
     */
    public function members(): HasMany
    {
        return $this->hasMany(ListenRoomMember::class);
    }

    /**
     * Active members (not yet left).
     */
    public function activeMembers(): HasMany
    {
        return $this->hasMany(ListenRoomMember::class)->whereNull('left_at');
    }

    /**
     * The host member of this room.
     */
    public function host(): HasOne
    {
        return $this->hasOne(ListenRoomMember::class)->where('role', 'host');
    }

    /**
     * Active synced listeners (not host, not left).
     */
    public function activeSyncedListeners(): HasMany
    {
        return $this->hasMany(ListenRoomMember::class)
            ->where('role', 'listener')
            ->whereNull('left_at');
    }

    /**
     * Check if this room is still active.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * Check if this room ever had listeners (excluding host).
     */
    public function getHadListenersAttribute(): bool
    {
        return $this->members()->where('role', 'listener')->exists();
    }
}
