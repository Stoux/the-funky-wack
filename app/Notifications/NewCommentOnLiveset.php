<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Liveset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewCommentOnLiveset extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Comment $comment,
        public Liveset $liveset
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_comment',
            'comment_id' => $this->comment->id,
            'liveset_id' => $this->liveset->id,
            'liveset_title' => $this->liveset->title,
            'commenter_name' => $this->comment->user?->name,
            'body_preview' => str($this->comment->body)->limit(80)->toString(),
            'timestamp_seconds' => $this->comment->timestamp_seconds,
        ];
    }
}
