<?php

use App\Models\InviteCode;
use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertOk();
});

test('new users can register with a valid invite code', function () {
    $inviter = User::factory()->create();
    $invite = InviteCode::create([
        'user_id' => $inviter->id,
        'code' => InviteCode::generateCode(),
    ]);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invite_code' => $invite->code,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('user.profile'));
});

test('new users cannot register without an invite code', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors(['invite_code']);
});

test('new users cannot register with an invalid invite code', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invite_code' => 'INVALID1',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors(['invite_code']);
});
