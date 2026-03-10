<?php

use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->user = User::factory()->create(['is_admin' => false]);
});

function actingAsAdmin($test)
{
    return $test->withSession(['admin_authenticated_at' => now()])->actingAs($test->admin);
}

describe('GET /admin/users', function () {
    it('lists all users for admins', function () {
        $response = actingAsAdmin($this)->get('/admin/users');

        $response->assertOk();
    });

    it('redirects non-admin users to login', function () {
        $response = $this->actingAs($this->user)->get('/admin/users');

        $response->assertRedirect('/login');
    });

    it('redirects guests to login', function () {
        $response = $this->get('/admin/users');

        $response->assertRedirect('/login');
    });
});

describe('GET /admin/users/new', function () {
    it('renders the create user form', function () {
        $response = actingAsAdmin($this)->get('/admin/users/new');

        $response->assertOk();
    });
});

describe('POST /admin/users', function () {
    it('creates a new user', function () {
        $response = actingAsAdmin($this)->post('/admin/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'is_admin' => false,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'is_admin' => false,
        ]);
    });

    it('creates an admin user', function () {
        $response = actingAsAdmin($this)->post('/admin/users', [
            'name' => 'Admin User',
            'email' => 'admin-new@example.com',
            'password' => 'password123',
            'is_admin' => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'admin-new@example.com',
            'is_admin' => true,
        ]);
    });

    it('validates required fields', function () {
        $response = actingAsAdmin($this)->post('/admin/users', []);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
    });

    it('validates unique email', function () {
        $response = actingAsAdmin($this)->post('/admin/users', [
            'name' => 'Duplicate',
            'email' => $this->user->email,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
    });
});

describe('GET /admin/users/{user}', function () {
    it('renders the edit user form', function () {
        $response = actingAsAdmin($this)->get("/admin/users/{$this->user->id}");

        $response->assertOk();
    });
});

describe('PATCH /admin/users/{user}', function () {
    it('updates a user', function () {
        $response = actingAsAdmin($this)->patch("/admin/users/{$this->user->id}", [
            'name' => 'Updated Name',
            'email' => $this->user->email,
            'is_admin' => false,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Updated Name',
        ]);
    });

    it('updates a user password', function () {
        $response = actingAsAdmin($this)->patch("/admin/users/{$this->user->id}", [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'password' => 'newpassword123',
            'is_admin' => false,
        ]);

        $response->assertRedirect();
    });

    it('skips password update when blank', function () {
        $oldHash = $this->user->password;

        actingAsAdmin($this)->patch("/admin/users/{$this->user->id}", [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'password' => '',
            'is_admin' => false,
        ]);

        expect($this->user->fresh()->password)->toBe($oldHash);
    });

    it('toggles admin status', function () {
        actingAsAdmin($this)->patch("/admin/users/{$this->user->id}", [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'is_admin' => true,
        ]);

        expect($this->user->fresh()->is_admin)->toBeTrue();
    });

    it('validates unique email ignoring current user', function () {
        $response = actingAsAdmin($this)->patch("/admin/users/{$this->user->id}", [
            'name' => $this->user->name,
            'email' => $this->admin->email,
            'is_admin' => false,
        ]);

        $response->assertSessionHasErrors(['email']);
    });
});

describe('DELETE /admin/users/{user}', function () {
    it('deletes a user', function () {
        $userId = $this->user->id;

        $response = actingAsAdmin($this)->delete("/admin/users/{$userId}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    });
});
