<?php

namespace App\Events;

use App\Models\Comment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base for the realtime comment events on public channel comments.liveset.{id}.
 * Public, because comments are public through the API as well, so guests can follow along.
 * Queued, so a Reverb hiccup can't fail the request after the comment is already stored.
 */
abstract class CommentBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Comment $comment) {}

    abstract public function broadcastAs(): string;

    public function broadcastOn(): array
    {
        return [
            new Channel('comments.liveset.'.$this->comment->liveset_id),
        ];
    }

    public function broadcastWith(): array
    {
        return $this->comment->toBroadcastPayload();
    }
}
