<?php

namespace App\Events;

use App\Models\Comment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An edited or restored comment, see CommentPosted for the channel.
 */
class CommentUpdated implements ShouldBroadcast
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
        return 'comment.updated';
    }

    public function broadcastWith(): array
    {
        return $this->comment->toBroadcastPayload();
    }
}
