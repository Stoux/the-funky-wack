<?php

use App\Http\Middleware\AdminLoggedIn;
use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home'));
});

test('admins get a fresh-auth timestamp on login and can then reach the intended admin page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->withSession(['url.intended' => '/admin/users'])->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    expect(session(AdminLoggedIn::SESSION_KEY))->not->toBeNull();
    $response->assertRedirect('/admin/users');
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'web')->post('/logout');

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});
