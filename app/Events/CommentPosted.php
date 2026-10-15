<?php

namespace App\Events;

use App\Models\Comment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Queued, so a Reverb hiccup can't fail the request after the comment is already stored.
 */
class CommentPosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Comment $comment) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('liveset.'.$this->comment->liveset_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'comment.posted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->comment->id,
            'user' => $this->comment->user ? [
                'id' => $this->comment->user->id,
                'name' => $this->comment->user->name,
            ] : null,
            'body' => $this->comment->body,
            'is_deleted' => false,
            'timestamp_seconds' => $this->comment->timestamp_seconds,
            'parent_id' => $this->comment->parent_id,
            'created_at' => $this->comment->created_at->toIso8601String(),
            'updated_at' => $this->comment->updated_at->toIso8601String(),
        ];
    }
}
