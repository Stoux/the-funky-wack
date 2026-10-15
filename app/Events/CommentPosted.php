<?php

namespace App\Events;

class CommentPosted extends CommentBroadcast
{
    public function broadcastAs(): string
    {
        return 'comment.posted';
    }
}
