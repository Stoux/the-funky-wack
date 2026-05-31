<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'listening_visibility',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Invite codes created by this user.
     */
    public function inviteCodes(): HasMany
    {
        return $this->hasMany(InviteCode::class);
    }

    /**
     * The invite code this user used to register.
     */
    public function usedInvite(): HasOne
    {
        return $this->hasOne(InviteCode::class, 'used_by');
    }

    /**
     * Play history for this user.
     */
    public function playHistory(): HasMany
    {
        return $this->hasMany(PlayHistory::class);
    }

    /**
     * Saved playback positions for this user.
     */
    public function playbackPositions(): HasMany
    {
        return $this->hasMany(PlaybackPosition::class);
    }

    /**
     * Favorites for this user.
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Livesets favorited by this user.
     */
    public function favoriteLivesets()
    {
        return $this->belongsToMany(Liveset::class, 'favorites')
            ->withTimestamps();
    }

    /**
     * Playlists owned by this user.
     */
    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }

    /**
     * Devices registered for this user.
     */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /**
     * Get or create a device for this user.
     * Restores soft-deleted devices if they come back.
     */
    public function getOrCreateDevice(
        string $clientId,
        ?string $userAgent = null,
        ?string $deviceName = null,
        ?string $deviceType = null,
    ): UserDevice {
        // Check for existing device (including soft-deleted)
        $device = $this->devices()->withTrashed()->where('client_id', $clientId)->first();

        if ($device) {
            // Restore if soft-deleted
            if ($device->trashed()) {
                $device->restore();
            }

            // Refresh client-supplied fingerprint when the caller knows better
            // than UA parsing (e.g. native app login sends Build.MODEL +
            // FEATURE_AUTOMOTIVE/LEANBACK derived device_type). Skip when null
            // so anonymous UA-parsed names aren't clobbered on every request.
            $updates = ['last_seen_at' => now()];
            if ($deviceName !== null && $device->device_name !== $deviceName) {
                $updates['device_name'] = $deviceName;
            }
            if ($deviceType !== null && $device->device_type !== $deviceType) {
                $updates['device_type'] = $deviceType;
            }
            $device->update($updates);

            return $device;
        }

        // Create new device
        return $this->devices()->create([
            'client_id' => $clientId,
            'device_type' => $deviceType ?? UserDevice::detectDeviceType($userAgent),
            'device_name' => $deviceName ?? UserDevice::detectDeviceName($userAgent),
            'user_agent' => $userAgent,
            'last_seen_at' => now(),
        ]);
    }
}
