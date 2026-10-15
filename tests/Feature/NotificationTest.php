<?php

use App\Events\CommentPosted;
use App\Models\Comment;
use App\Models\Liveset;
use App\Models\User;
use App\Notifications\CommentReply;
use App\Notifications\NewCommentOnLiveset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->liveset = Liveset::factory()->create(['duration_in_seconds' => 3600]);
});

describe('comment notifications', function () {
    it('sends CommentReply notification when replying to someone else', function () {
        Notification::fake();
        Event::fake([CommentPosted::class]);

        $parentAuthor = User::factory()->create();
        $parent = Comment::factory()->create([
            'liveset_id' => $this->liveset->id,
            'user_id' => $parentAuthor->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/livesets/{$this->liveset->id}/comments", [
                'body' => 'Great point!',
                'timestamp_seconds' => 100,
                'parent_id' => $parent->id,
            ]);

        Notification::assertSentTo($parentAuthor, CommentReply::class);
    });

    it('sends NewCommentOnLiveset to other commenters', function () {
        Notification::fake();
        Event::fake([CommentPosted::class]);

        $otherUser = User::factory()->create();
        Comment::factory()->create([
            'liveset_id' => $this->liveset->id,
            'user_id' => $otherUser->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/livesets/{$this->liveset->id}/comments", [
                'body' => 'New comment here!',
                'timestamp_seconds' => 200,
            ]);

        Notification::assertSentTo($otherUser, NewCommentOnLiveset::class);
    });

    it('does not send self-notification', function () {
        Notification::fake();
        Event::fake([CommentPosted::class]);

        // User already has a comment on this liveset
        Comment::factory()->create([
            'liveset_id' => $this->liveset->id,
            'user_id' => $this->user->id,
        ]);

        // Same user posts another comment
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/livesets/{$this->liveset->id}/comments", [
                'body' => 'Another one!',
                'timestamp_seconds' => 300,
            ]);

        Notification::assertNotSentTo($this->user, NewCommentOnLiveset::class);
    });
});

describe('GET /api/notifications', function () {
    it('returns notifications with unread count', function () {
        $this->user->notify(new NewCommentOnLiveset(
            Comment::factory()->create(['liveset_id' => $this->liveset->id]),
            $this->liveset,
        ));

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('unread_count', 1);
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/notifications');
        $response->assertUnauthorized();
    });
});

describe('PUT /api/notifications/{id}/read', function () {
    it('marks notification as read', function () {
        $this->user->notify(new NewCommentOnLiveset(
            Comment::factory()->create(['liveset_id' => $this->liveset->id]),
            $this->liveset,
        ));

        $notification = $this->user->notifications()->first();

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/notifications/{$notification->id}/read");

        $response->assertOk();
        expect($notification->fresh()->read_at)->not->toBeNull();
    });
});

describe('PUT /api/notifications/read-all', function () {
    it('marks all notifications as read', function () {
        $this->user->notify(new NewCommentOnLiveset(
            Comment::factory()->create(['liveset_id' => $this->liveset->id]),
            $this->liveset,
        ));
        $this->user->notify(new NewCommentOnLiveset(
            Comment::factory()->create(['liveset_id' => $this->liveset->id]),
            $this->liveset,
        ));

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/notifications/read-all');

        $response->assertOk();
        expect($this->user->unreadNotifications()->count())->toBe(0);
    });
});
