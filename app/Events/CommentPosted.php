<?php

namespace App\Events;

use App\Models\Comment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Queued, so a Reverb hiccup can't fail the request after the comment is already stored.
 * Public channel: comments are public through the API as well, so guests can follow along.
 */
class CommentPosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Comment $comment) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('comments.liveset.'.$this->comment->liveset_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'comment.posted';
    }

    public function broadcastWith(): array
    {
        return $this->comment->toBroadcastPayload();
    }
}
