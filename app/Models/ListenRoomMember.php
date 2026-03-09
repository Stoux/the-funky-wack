<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListenRoomMember extends Model
{
    protected $fillable = [
        'listen_room_id',
        'user_id',
        'session_id',
        'client_id',
        'role',
        'mode',
        'play_history_id',
        'joined_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    /**
     * The listen room this member belongs to.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(ListenRoom::class, 'listen_room_id');
    }

    /**
     * The user account (null for anonymous).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The play history record for this member's listening.
     */
    public function playHistory(): BelongsTo
    {
        return $this->belongsTo(PlayHistory::class);
    }
}
