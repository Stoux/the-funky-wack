<?php

namespace App\Events;

/**
 * Soft deleted. Clients drop a top-level comment without replies, anything else becomes a [deleted] placeholder
 * (same as the index endpoint).
 */
class CommentDeleted extends CommentBroadcast
{
    public function broadcastAs(): string
    {
        return 'comment.deleted';
    }
}
