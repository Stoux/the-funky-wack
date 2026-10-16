<?php

use App\Http\Middleware\AdminLoggedIn;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Laravel\Passkeys\Events\PasskeyVerified;
use Laravel\Passkeys\Passkey;

function makePasskey(User $user, string $name = 'MacBook'): Passkey
{
    return $user->passkeys()->create([
        'name' => $name,
        'credential_id' => 'credential-'.fake()->unique()->uuid(),
        'credential' => [],
    ]);
}

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('managing passkeys', function () {
    it('lists only your own passkeys on the profile page', function () {
        makePasskey($this->user, 'MacBook');
        makePasskey(User::factory()->create(), 'Not mine');

        $this->actingAs($this->user)->get(route('user.profile'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('passkeys', 1)
                ->where('passkeys.0.name', 'MacBook')
                ->where('passkeys.0.last_used_at', null));
    });

    it('lets you remove your own passkey, but not someone else\'s', function () {
        $mine = makePasskey($this->user);
        $theirs = makePasskey(User::factory()->create());

        // The package routes use the web guard, like a normal login
        $this->actingAs($this->user, 'web')->deleteJson("/user/passkeys/{$theirs->id}")->assertForbidden();
        $this->actingAs($this->user, 'web')->deleteJson("/user/passkeys/{$mine->id}")->assertSuccessful();

        expect(Passkey::find($mine->id))->toBeNull()
            ->and(Passkey::find($theirs->id))->not->toBeNull();
    });

    it('gives registration options to logged in users only', function () {
        $this->getJson('/user/passkeys/options')->assertUnauthorized();

        $this->actingAs($this->user, 'web')->getJson('/user/passkeys/options')
            ->assertOk()
            ->assertJsonStructure(['options' => ['challenge', 'rp', 'user']]);
    });
});

describe('signing in with a passkey', function () {
    it('hands out login options to guests', function () {
        $this->getJson('/passkeys/login/options')
            ->assertOk()
            ->assertJsonStructure(['options' => ['challenge', 'rpId']])
            ->assertJsonPath('options.allowCredentials', []);
    });

    it('counts as the fresh login the admin area asks for, for admins only', function () {
        $admin = User::factory()->create(['is_admin' => true]);

        event(new PasskeyVerified($this->user, new Passkey));
        expect(session(AdminLoggedIn::SESSION_KEY))->toBeNull();

        event(new PasskeyVerified($admin, new Passkey));
        expect(session(AdminLoggedIn::SESSION_KEY))->not->toBeNull();
    });

    it('rejects a made up credential', function () {
        $this->getJson('/passkeys/login/options')->assertOk();

        $this->postJson('/passkeys/login', ['credential' => ['id' => 'nope', 'rawId' => 'nope', 'type' => 'public-key', 'response' => []]])
            ->assertStatus(422);

        $this->assertGuest();
    });
});
