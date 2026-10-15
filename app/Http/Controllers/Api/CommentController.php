<?php

namespace App\Http\Controllers\Api;

use App\Events\CommentDeleted;
use App\Events\CommentPosted;
use App\Events\CommentUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Models\Comment;
use App\Models\Liveset;
use App\Models\User;
use App\Notifications\CommentReply;
use App\Notifications\NewCommentOnLiveset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class CommentController extends Controller
{
    public function index(Request $request, Liveset $liveset): JsonResponse
    {
        $sortBy = $request->query('sort') === 'date' ? 'created_at' : 'timestamp_seconds';
        $userId = $request->user()?->id;

        $comments = Comment::query()
            ->withTrashed()
            ->where('liveset_id', $liveset->id)
            ->whereNull('parent_id')
            ->with(['user:id,name', 'replies' => function ($query) {
                $query->withTrashed()->with('user:id,name')->orderBy('created_at');
            }])
            ->orderBy($sortBy)
            ->get()
            ->reject(fn (Comment $comment) => $comment->trashed() && $comment->replies->isEmpty())
            ->values()
            ->map(fn (Comment $comment) => $this->formatComment($comment, $userId));

        return response()->json(['comments' => $comments]);
    }

    public function store(StoreCommentRequest $request, Liveset $liveset): JsonResponse
    {
        $parent = $request->parent_id ? Comment::find($request->parent_id) : null;

        $comment = Comment::create([
            'liveset_id' => $liveset->id,
            'user_id' => $request->user()->id,
            'parent_id' => $parent?->id,
            'body' => $request->body,
            'timestamp_seconds' => $parent ? $parent->timestamp_seconds : $request->timestamp_seconds,
        ]);

        $comment->load('user:id,name');

        broadcast(new CommentPosted($comment))->toOthers();
        $this->sendNotifications($comment, $liveset, $parent);

        return response()->json([
            'comment' => $this->formatComment($comment, $request->user()->id),
        ], 201);
    }

    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        $comment->update(['body' => $request->body]);
        $comment->load('user:id,name');

        broadcast(new CommentUpdated($comment))->toOthers();

        return response()->json([
            'comment' => $this->formatComment($comment, $request->user()->id),
        ]);
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $user = $request->user();

        if ($user->id !== $comment->user_id && ! $user->is_admin) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $comment->delete();
        broadcast(new CommentDeleted($comment))->toOthers();

        return response()->json(['message' => 'Comment deleted.']);
    }

    public function recent(Request $request): JsonResponse
    {
        $comments = Comment::query()
            ->with(['user:id,name', 'liveset:id,title,artist_name'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Comment $comment) => [
                'id' => $comment->id,
                'user' => $comment->user ? ['id' => $comment->user->id, 'name' => $comment->user->name] : null,
                'liveset' => $comment->liveset ? [
                    'id' => $comment->liveset->id,
                    'title' => $comment->liveset->title,
                    'artist_name' => $comment->liveset->artist_name,
                ] : null,
                'body' => $comment->body,
                'timestamp_seconds' => $comment->timestamp_seconds,
                'created_at' => $comment->created_at->toIso8601String(),
            ]);

        return response()->json(['comments' => $comments]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatComment(Comment $comment, ?int $currentUserId): array
    {
        $data = [
            ...$comment->toBroadcastPayload(),
            'is_own' => $currentUserId !== null && $comment->user_id === $currentUserId,
        ];

        if ($comment->relationLoaded('replies')) {
            $data['replies'] = $comment->replies->map(fn (Comment $reply) => $this->formatComment($reply, $currentUserId))->values()->toArray();
        }

        return $data;
    }

    /**
     * A reply notifies the parent's author, every comment notifies the other commenters on the liveset
     * (except the parent's author, who already got the reply notification).
     */
    protected function sendNotifications(Comment $comment, Liveset $liveset, ?Comment $parent): void
    {
        $commenterId = $comment->user_id;

        if ($parent && $parent->user_id !== $commenterId) {
            $parent->user?->notify(new CommentReply($comment, $liveset));
        }

        $otherCommenters = User::whereIn(
            'id',
            Comment::where('liveset_id', $liveset->id)
                ->where('user_id', '!=', $commenterId)
                ->when($parent?->user_id, fn ($query, $parentUserId) => $query->where('user_id', '!=', $parentUserId))
                ->distinct()
                ->pluck('user_id')
        )->get();

        if ($otherCommenters->isNotEmpty()) {
            Notification::send($otherCommenters, new NewCommentOnLiveset($comment, $liveset));
        }
    }
}
