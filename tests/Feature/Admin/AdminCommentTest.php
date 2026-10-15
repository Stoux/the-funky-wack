<?php

use App\Models\Comment;
use App\Models\Liveset;
use App\Models\User;

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
});
