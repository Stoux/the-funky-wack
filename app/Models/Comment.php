<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

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

    /**
     * Payload for the realtime comment events: the API's comment shape plus parent_id, minus the per-user is_own.
     *
     * @return array{id: int, user: array{id: int, name: string}|null, body: string|null, is_deleted: bool, timestamp_seconds: int, parent_id: int|null, created_at: string, updated_at: string}
     */
    public function toBroadcastPayload(): array
    {
        return [
            'id' => $this->id,
            'user' => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null,
            'body' => $this->trashed() ? null : $this->body,
            'is_deleted' => $this->trashed(),
            'timestamp_seconds' => $this->timestamp_seconds,
            'parent_id' => $this->parent_id,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
