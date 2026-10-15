<?php

namespace App\Events;

/**
 * Restored by an admin. Clients either un-delete the placeholder or add it back if they had dropped it.
 */
class CommentRestored extends CommentBroadcast
{
    public function broadcastAs(): string
    {
        return 'comment.restored';
    }
}
