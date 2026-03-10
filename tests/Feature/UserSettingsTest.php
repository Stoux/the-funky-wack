<?php

use App\Models\InviteCode;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('PUT /api/settings/profile', function () {
    it('updates name and email', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/settings/profile', [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Profile updated.',
                'user' => [
                    'name' => 'New Name',
                    'email' => 'new@example.com',
                ],
            ]);

        expect($this->user->fresh()->name)->toBe('New Name');
        expect($this->user->fresh()->email)->toBe('new@example.com');
    });

    it('validates required fields', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/settings/profile', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    });

    it('validates unique email excluding current user', function () {
        $other = User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/settings/profile', [
                'name' => 'Test',
                'email' => 'taken@example.com',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('allows keeping the same email', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/settings/profile', [
                'name' => 'Updated',
                'email' => $this->user->email,
            ]);

        $response->assertOk();
    });

    it('requires authentication', function () {
        $response = $this->putJson('/api/settings/profile', [
            'name' => 'Test',
            'email' => 'test@example.com',
        ]);

        $response->assertUnauthorized();
    });
});

describe('PUT /api/settings/password', function () {
    it('updates password with valid current password', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/settings/password', [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertOk()
            ->assertJson(['message' => 'Password updated.']);

        expect(Hash::check('new-password-123', $this->user->fresh()->password))->toBeTrue();
    });

    it('rejects wrong current password', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/settings/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
    });

    it('rejects mismatched confirmation', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/settings/password', [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'different-password',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    });

    it('enforces minimum password length', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/settings/password', [
                'current_password' => 'password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    });

    it('requires authentication', function () {
        $response = $this->putJson('/api/settings/password', [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertUnauthorized();
    });
});

describe('DELETE /api/invites/{invite}', function () {
    it('revokes an unused invite code', function () {
        $invite = $this->user->inviteCodes()->create([
            'code' => InviteCode::generateCode(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/invites/{$invite->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Invite code revoked.']);

        expect(InviteCode::find($invite->id))->toBeNull();
    });

    it('cannot revoke a used invite code', function () {
        $other = User::factory()->create();
        $invite = $this->user->inviteCodes()->create([
            'code' => InviteCode::generateCode(),
            'used_by' => $other->id,
            'used_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/invites/{$invite->id}");

        $response->assertStatus(409);
        expect(InviteCode::find($invite->id))->not->toBeNull();
    });

    it('cannot revoke another user\'s invite code', function () {
        $other = User::factory()->create();
        $invite = $other->inviteCodes()->create([
            'code' => InviteCode::generateCode(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/invites/{$invite->id}");

        $response->assertForbidden();
    });

    it('requires authentication', function () {
        $invite = $this->user->inviteCodes()->create([
            'code' => InviteCode::generateCode(),
        ]);

        $response = $this->deleteJson("/api/invites/{$invite->id}");

        $response->assertUnauthorized();
    });
});
