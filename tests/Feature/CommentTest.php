<?php

use App\Events\CommentDeleted;
use App\Events\CommentPosted;
use App\Events\CommentRestored;
use App\Events\CommentUpdated;
use App\Models\Comment;
use App\Models\Liveset;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->liveset = Liveset::factory()->create(['duration_in_seconds' => 3600]);
});

describe('GET /api/livesets/{liveset}/comments', function () {
    it('returns comments sorted by timestamp', function () {
        $c1 = Comment::factory()->create(['liveset_id' => $this->liveset->id, 'timestamp_seconds' => 300]);
        $c2 = Comment::factory()->create(['liveset_id' => $this->liveset->id, 'timestamp_seconds' => 100]);

        $response = $this->getJson("/api/livesets/{$this->liveset->id}/comments");

        $response->assertOk()
            ->assertJsonCount(2, 'comments');

        expect($response->json('comments.0.id'))->toBe($c2->id);
        expect($response->json('comments.1.id'))->toBe($c1->id);
    });

    it('returns comments sorted by date when requested', function () {
        $c1 = Comment::factory()->create([
            'liveset_id' => $this->liveset->id,
            'timestamp_seconds' => 100,
            'created_at' => now()->subHour(),
        ]);
        $c2 = Comment::factory()->create([
            'liveset_id' => $this->liveset->id,
            'timestamp_seconds' => 200,
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/livesets/{$this->liveset->id}/comments?sort=date");

        $response->assertOk();
        expect($response->json('comments.0.id'))->toBe($c1->id);
    });

    it('includes replies', function () {
        $parent = Comment::factory()->create(['liveset_id' => $this->liveset->id]);
        Comment::factory()->reply($parent)->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/livesets/{$this->liveset->id}/comments");

        $response->assertOk()
            ->assertJsonCount(1, 'comments')
            ->assertJsonCount(1, 'comments.0.replies');
    });

    it('shows deleted placeholder when deleted comment has replies', function () {
        $parent = Comment::factory()->create(['liveset_id' => $this->liveset->id]);
        Comment::factory()->reply($parent)->create();
        $parent->delete();

        $response = $this->getJson("/api/livesets/{$this->liveset->id}/comments");

        $response->assertOk()
            ->assertJsonCount(1, 'comments');
        expect($response->json('comments.0.is_deleted'))->toBeTrue();
        expect($response->json('comments.0.body'))->toBeNull();
    });

    it('excludes deleted comments without replies', function () {
        $comment = Comment::factory()->create(['liveset_id' => $this->liveset->id]);
        $comment->delete();

        $response = $this->getJson("/api/livesets/{$this->liveset->id}/comments");

        $response->assertOk()
            ->assertJsonCount(0, 'comments');
    });

    it('is accessible to guests', function () {
        Comment::factory()->create(['liveset_id' => $this->liveset->id]);

        $response = $this->getJson("/api/livesets/{$this->liveset->id}/comments");

        $response->assertOk()
            ->assertJsonCount(1, 'comments');
    });
});

describe('POST /api/livesets/{liveset}/comments', function () {
    it('creates a comment', function () {
        Event::fake([CommentPosted::class]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/livesets/{$this->liveset->id}/comments", [
                'body' => 'Great set!',
                'timestamp_seconds' => 120,
            ]);

        $response->assertCreated()
            ->assertJsonPath('comment.body', 'Great set!')
            ->assertJsonPath('comment.timestamp_seconds', 120)
            ->assertJsonPath('comment.is_own', true);

        expect(Comment::count())->toBe(1);
    });

    it('dispatches CommentPosted event', function () {
        Event::fake([CommentPosted::class]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/livesets/{$this->liveset->id}/comments", [
                'body' => 'Test',
                'timestamp_seconds' => 0,
            ]);

        Event::assertDispatched(CommentPosted::class);
    });

    it('requires authentication', function () {
        $response = $this->postJson("/api/livesets/{$this->liveset->id}/comments", [
            'body' => 'Test',
            'timestamp_seconds' => 0,
        ]);

        $response->assertUnauthorized();
    });

    it('validates body is required', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/livesets/{$this->liveset->id}/comments", [
                'timestamp_seconds' => 0,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    });

    it('validates body max length', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/livesets/{$this->liveset->id}/comments", [
                'body' => str_repeat('a', 2001),
                'timestamp_seconds' => 0,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    });

    it('validates timestamp is required', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/livesets/{$this->liveset->id}/comments", [
                'body' => 'Test',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['timestamp_seconds']);
    });

    it('validates timestamp does not exceed duration', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/livesets/{$this->liveset->id}/comments", [
                'body' => 'Test',
                'timestamp_seconds' => 9999,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['timestamp_seconds']);
    });

    it('reply inherits parent timestamp', function () {
        Event::fake([CommentPosted::class]);
        $parent = Comment::factory()->create([
            'liveset_id' => $this->liveset->id,
            'timestamp_seconds' => 500,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/livesets/{$this->liveset->id}/comments", [
                'body' => 'Nice!',
                'timestamp_seconds' => 0,
                'parent_id' => $parent->id,
            ]);

        $response->assertCreated();
        expect(Comment::latest('id')->first()->timestamp_seconds)->toBe(500);
    });

    it('rejects nested reply', function () {
        $parent = Comment::factory()->create(['liveset_id' => $this->liveset->id]);
        $reply = Comment::factory()->reply($parent)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/livesets/{$this->liveset->id}/comments", [
                'body' => 'Nested!',
                'timestamp_seconds' => 0,
                'parent_id' => $reply->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    });

    it('rejects reply on wrong liveset', function () {
        $otherLiveset = Liveset::factory()->create();
        $parent = Comment::factory()->create(['liveset_id' => $otherLiveset->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/livesets/{$this->liveset->id}/comments", [
                'body' => 'Wrong liveset!',
                'timestamp_seconds' => 0,
                'parent_id' => $parent->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    });

    it('rejects reply to a deleted comment instead of storing it as top-level', function () {
        $parent = Comment::factory()->create(['liveset_id' => $this->liveset->id]);
        $parent->delete();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/livesets/{$this->liveset->id}/comments", [
                'body' => 'Too late!',
                'timestamp_seconds' => 0,
                'parent_id' => $parent->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
        expect(Comment::where('body', 'Too late!')->exists())->toBeFalse();
    });

    it('queues the CommentPosted broadcast instead of sending it during the request', function () {
        expect(new CommentPosted(Comment::factory()->create(['liveset_id' => $this->liveset->id])))
            ->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class)
            ->not->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class);
    });
});

describe('PUT /api/comments/{comment}', function () {
    it('owner can edit comment', function () {
        $comment = Comment::factory()->create(['user_id' => $this->user->id, 'liveset_id' => $this->liveset->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/comments/{$comment->id}", [
                'body' => 'Updated!',
            ]);

        $response->assertOk()
            ->assertJsonPath('comment.body', 'Updated!');
    });

    it('non-owner cannot edit comment', function () {
        $other = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $other->id, 'liveset_id' => $this->liveset->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/comments/{$comment->id}", [
                'body' => 'Hijack!',
            ]);

        $response->assertForbidden();
    });

    it('validates body is required', function () {
        $comment = Comment::factory()->create(['user_id' => $this->user->id, 'liveset_id' => $this->liveset->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/comments/{$comment->id}", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    });
});

describe('DELETE /api/comments/{comment}', function () {
    it('owner can delete comment', function () {
        $comment = Comment::factory()->create(['user_id' => $this->user->id, 'liveset_id' => $this->liveset->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/comments/{$comment->id}");

        $response->assertOk();
        expect(Comment::find($comment->id))->toBeNull();
        expect(Comment::withTrashed()->find($comment->id))->not->toBeNull();
    });

    it('admin can delete any comment', function () {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $comment = Comment::factory()->create(['liveset_id' => $this->liveset->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/comments/{$comment->id}");

        $response->assertOk();
    });

    it('non-owner cannot delete comment', function () {
        $other = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $other->id, 'liveset_id' => $this->liveset->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/comments/{$comment->id}");

        $response->assertForbidden();
    });
});

describe('GET /api/comments/recent', function () {
    it('returns the latest comments in the shared shape with their liveset, without deleted ones', function () {
        $older = Comment::factory()->create(['liveset_id' => $this->liveset->id, 'created_at' => now()->subMinute()]);
        $newer = Comment::factory()->create(['liveset_id' => $this->liveset->id]);
        Comment::factory()->create(['liveset_id' => $this->liveset->id])->delete();

        $response = $this->getJson('/api/comments/recent');

        $response->assertOk()->assertJsonCount(2, 'comments');
        expect($response->json('comments.0'))->toMatchArray([
            ...$newer->fresh()->toBroadcastPayload(),
            'liveset' => [
                'id' => $this->liveset->id,
                'title' => $this->liveset->title,
                'artist_name' => $this->liveset->artist_name,
            ],
        ])->and($response->json('comments.1.id'))->toBe($older->id);
    });
});

describe('realtime comment events', function () {
    it('broadcasts posts, edits and deletes to others on the public liveset channel', function () {
        Event::fake([CommentPosted::class, CommentUpdated::class, CommentDeleted::class]);

        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Socket-ID', '1234.5678')
            ->postJson("/api/livesets/{$this->liveset->id}/comments", ['body' => 'Live!', 'timestamp_seconds' => 42]);
        $comment = Comment::where('body', 'Live!')->firstOrFail();

        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Socket-ID', '1234.5678')
            ->putJson("/api/comments/{$comment->id}", ['body' => 'Live, edited!']);

        $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Socket-ID', '1234.5678')
            ->deleteJson("/api/comments/{$comment->id}");

        $expectations = [
            CommentPosted::class => ['comment.posted', 'Live!', false],
            CommentUpdated::class => ['comment.updated', 'Live, edited!', false],
            CommentDeleted::class => ['comment.deleted', null, true],
        ];
        foreach ($expectations as $eventClass => [$name, $body, $isDeleted]) {
            Event::assertDispatched($eventClass, function ($event) use ($comment, $name, $body, $isDeleted) {
                $channel = $event->broadcastOn()[0];
                $payload = $event->broadcastWith();

                return $channel instanceof Channel
                    && ! $channel instanceof PrivateChannel
                    && $channel->name === "comments.liveset.{$this->liveset->id}"
                    && $event->broadcastAs() === $name
                    && $event->socket === '1234.5678'
                    && $payload['id'] === $comment->id
                    && $payload['body'] === $body
                    && $payload['is_deleted'] === $isDeleted
                    && $payload['timestamp_seconds'] === 42
                    && ! array_key_exists('is_own', $payload);
            });
        }
    });

    it('queues all comment broadcasts instead of sending them during the request', function (string $eventClass) {
        $comment = Comment::factory()->create(['liveset_id' => $this->liveset->id]);

        expect(new $eventClass($comment))
            ->toBeInstanceOf(ShouldBroadcast::class)
            ->not->toBeInstanceOf(ShouldBroadcastNow::class);
    })->with([CommentPosted::class, CommentUpdated::class, CommentDeleted::class, CommentRestored::class]);

    it('uses the realtime payload as the API comment shape, plus is_own', function () {
        $parent = Comment::factory()->create(['liveset_id' => $this->liveset->id, 'user_id' => $this->user->id]);
        $reply = Comment::factory()->reply($parent)->create();

        $response = $this->actingAs($this->user, 'sanctum')->getJson("/api/livesets/{$this->liveset->id}/comments");

        expect($response->json('comments.0'))->toMatchArray([...$parent->fresh()->toBroadcastPayload(), 'is_own' => true])
            ->and($response->json('comments.0.replies.0'))->toMatchArray([...$reply->fresh()->toBroadcastPayload(), 'is_own' => false]);
    });
});
