<?php

namespace App\Events;

class CommentUpdated extends CommentBroadcast
{
    public function broadcastAs(): string
    {
        return 'comment.updated';
    }
}
