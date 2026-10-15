<?php

use App\Events\CommentDeleted;
use App\Events\CommentRestored;
use App\Models\Comment;
use App\Models\Liveset;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->forceFill(['is_admin' => true])->save();
    $this->liveset = Liveset::factory()->create();
});

describe('admin comment management', function () {
    it('admin can view comments page', function () {
        Comment::factory()->create(['liveset_id' => $this->liveset->id]);

        $response = $this->actingAs($this->admin)
            ->withSession(['admin_authenticated_at' => now()])
            ->get(route('admin.comments'));

        $response->assertOk();
    });

    it('non-admin cannot view comments page', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('admin.comments'));

        $response->assertRedirect();
    });

    it('admin can delete a comment', function () {
        $comment = Comment::factory()->create(['liveset_id' => $this->liveset->id]);

        $response = $this->actingAs($this->admin)
            ->withSession(['admin_authenticated_at' => now()])
            ->delete(route('admin.comments.delete', $comment));

        $response->assertRedirect();
        expect(Comment::find($comment->id))->toBeNull();
        expect(Comment::withTrashed()->find($comment->id))->not->toBeNull();
    });

    it('admin can restore a deleted comment', function () {
        $comment = Comment::factory()->create(['liveset_id' => $this->liveset->id]);
        $comment->delete();

        $response = $this->actingAs($this->admin)
            ->withSession(['admin_authenticated_at' => now()])
            ->post(route('admin.comments.restore', $comment));

        $response->assertRedirect();
        expect(Comment::find($comment->id))->not->toBeNull();
    });

    it('paginates the comments page per 100', function () {
        Comment::factory()->count(101)->create(['liveset_id' => $this->liveset->id]);

        $response = $this->actingAs($this->admin)
            ->withSession(['admin_authenticated_at' => now()])
            ->get(route('admin.comments', ['page' => 2]));

        $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Comments')
            ->has('comments.data', 1)
            ->where('comments.current_page', 2)
            ->where('comments.last_page', 2)
            ->where('comments.total', 101)
        );
    });

    it('broadcasts deletes and restores to the public liveset channel', function () {
        Event::fake([CommentDeleted::class, CommentRestored::class]);
        $comment = Comment::factory()->create(['liveset_id' => $this->liveset->id]);

        $this->actingAs($this->admin)
            ->withSession(['admin_authenticated_at' => now()])
            ->delete(route('admin.comments.delete', $comment));
        $this->actingAs($this->admin)
            ->withSession(['admin_authenticated_at' => now()])
            ->post(route('admin.comments.restore', $comment));

        Event::assertDispatched(CommentDeleted::class, fn (CommentDeleted $event) => $event->comment->is($comment));
        Event::assertDispatched(CommentRestored::class, fn (CommentRestored $event) => $event->comment->is($comment)
            && $event->broadcastAs() === 'comment.restored'
            && $event->broadcastWith()['is_deleted'] === false);
    });
});
