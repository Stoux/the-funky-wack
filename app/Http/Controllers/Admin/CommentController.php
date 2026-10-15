<?php

namespace App\Http\Controllers\Admin;

use App\Events\CommentDeleted;
use App\Events\CommentUpdated;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use Inertia\Inertia;

class CommentController extends Controller
{
    public function comments(): \Inertia\Response
    {
        $comments = Comment::withTrashed()
            ->with(['user:id,name', 'liveset:id,title,artist_name'])
            ->orderByDesc('created_at')
            ->paginate(100)
            ->through(fn (Comment $comment) => [
                'id' => $comment->id,
                'user' => $comment->user ? ['id' => $comment->user->id, 'name' => $comment->user->name] : null,
                'liveset' => $comment->liveset ? ['id' => $comment->liveset->id, 'title' => $comment->liveset->title] : null,
                'body' => $comment->body,
                'timestamp_seconds' => $comment->timestamp_seconds,
                'is_deleted' => $comment->trashed(),
                'created_at' => $comment->created_at->toIso8601String(),
            ]);

        return Inertia::render('Admin/Comments', [
            'comments' => $comments,
        ]);
    }

    public function deleteComment(Comment $comment): \Illuminate\Http\RedirectResponse
    {
        $comment->delete();
        broadcast(new CommentDeleted($comment));

        return back()->with('success', 'Comment deleted.');
    }

    public function restoreComment(int $id): \Illuminate\Http\RedirectResponse
    {
        $comment = Comment::withTrashed()->findOrFail($id);
        $comment->restore();
        broadcast(new CommentUpdated($comment));

        return back()->with('success', 'Comment restored.');
    }
}
