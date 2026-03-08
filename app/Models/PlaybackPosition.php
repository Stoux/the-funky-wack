<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaybackPosition extends Model
{
    protected $fillable = [
        'user_id',
        'liveset_id',
        'client_id',
        'position',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function liveset(): BelongsTo
    {
        return $this->belongsTo(Liveset::class);
    }

    /**
     * Get the device associated with this position.
     * Uses the client_id to find the matching device.
     */
    public function device(): ?UserDevice
    {
        if (! $this->client_id) {
            return null;
        }

        return UserDevice::where('user_id', $this->user_id)
            ->where('client_id', $this->client_id)
            ->first();
    }
}
