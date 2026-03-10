<?php

use App\Models\DeviceCode;
use App\Models\User;
use App\Services\DeviceCodeService;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('POST /api/auth/device-code', function () {
    it('creates a device code', function () {
        $response = $this->postJson('/api/auth/device-code', [
            'device_name' => 'Living Room TV',
            'client_id' => 'abc123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['code', 'expires_at', 'poll_interval']);

        expect($response->json('code'))->toStartWith('TFW-');
        expect($response->json('poll_interval'))->toBe(5);

        $this->assertDatabaseHas('device_codes', [
            'client_id' => 'abc123',
            'device_name' => 'Living Room TV',
        ]);
    });

    it('validates required fields', function () {
        $response = $this->postJson('/api/auth/device-code', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['device_name', 'client_id']);
    });
});

describe('GET /api/auth/device-code/{code}/poll', function () {
    it('returns pending for unauthorized code', function () {
        $deviceCode = DeviceCode::factory()->create();

        $response = $this->getJson("/api/auth/device-code/{$deviceCode->code}/poll?client_id={$deviceCode->client_id}");

        $response->assertStatus(202)
            ->assertJson(['status' => 'pending']);
    });

    it('returns authorized with token after authorization', function () {
        $deviceCode = DeviceCode::factory()->create();

        app(DeviceCodeService::class)->authorize($deviceCode, $this->user);

        $response = $this->getJson("/api/auth/device-code/{$deviceCode->code}/poll?client_id={$deviceCode->client_id}");

        $response->assertOk()
            ->assertJson(['status' => 'authorized'])
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

        expect($response->json('user.id'))->toBe($this->user->id);
    });

    it('clears encrypted token after first successful poll', function () {
        $deviceCode = DeviceCode::factory()->create();

        app(DeviceCodeService::class)->authorize($deviceCode, $this->user);

        // First poll gets the token
        $this->getJson("/api/auth/device-code/{$deviceCode->code}/poll?client_id={$deviceCode->client_id}")
            ->assertOk()
            ->assertJson(['status' => 'authorized']);

        // Second poll returns expired (token consumed)
        $this->getJson("/api/auth/device-code/{$deviceCode->code}/poll?client_id={$deviceCode->client_id}")
            ->assertStatus(410)
            ->assertJson(['status' => 'expired']);
    });

    it('returns expired for expired code', function () {
        $deviceCode = DeviceCode::factory()->expired()->create();

        $response = $this->getJson("/api/auth/device-code/{$deviceCode->code}/poll?client_id={$deviceCode->client_id}");

        $response->assertStatus(410)
            ->assertJson(['status' => 'expired']);
    });

    it('returns expired for nonexistent code', function () {
        $response = $this->getJson('/api/auth/device-code/TFW-ZZZZZZ/poll?client_id=unknown');

        $response->assertStatus(410)
            ->assertJson(['status' => 'expired']);
    });

    it('returns expired for wrong client_id', function () {
        $deviceCode = DeviceCode::factory()->create();

        $response = $this->getJson("/api/auth/device-code/{$deviceCode->code}/poll?client_id=wrong-client");

        $response->assertStatus(410)
            ->assertJson(['status' => 'expired']);
    });

    it('requires client_id parameter', function () {
        $deviceCode = DeviceCode::factory()->create();

        $response = $this->getJson("/api/auth/device-code/{$deviceCode->code}/poll");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['client_id']);
    });
});

describe('POST /api/auth/device-code/{code}/authorize', function () {
    it('authorizes a pending device code', function () {
        $deviceCode = DeviceCode::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/auth/device-code/{$deviceCode->code}/authorize");

        $response->assertOk()
            ->assertJson([
                'message' => 'Device authorized successfully.',
                'device_name' => $deviceCode->device_name,
            ]);

        $this->assertDatabaseHas('device_codes', [
            'id' => $deviceCode->id,
            'user_id' => $this->user->id,
        ]);
    });

    it('returns 404 for expired code', function () {
        $deviceCode = DeviceCode::factory()->expired()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/auth/device-code/{$deviceCode->code}/authorize");

        $response->assertNotFound();
    });

    it('returns 409 for already authorized code', function () {
        $deviceCode = DeviceCode::factory()->create();

        app(DeviceCodeService::class)->authorize($deviceCode, $this->user);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/auth/device-code/{$deviceCode->code}/authorize");

        $response->assertStatus(409);
    });

    it('requires authentication', function () {
        $deviceCode = DeviceCode::factory()->create();

        $response = $this->postJson("/api/auth/device-code/{$deviceCode->code}/authorize");

        $response->assertUnauthorized();
    });
});

describe('GET /link', function () {
    it('renders the link device page for authenticated users', function () {
        $response = $this->actingAs($this->user)
            ->get('/link');

        $response->assertOk();
    });

    it('redirects guests to login', function () {
        $response = $this->get('/link');

        $response->assertRedirect('/login');
    });
});

describe('DeviceCode model', function () {
    it('generates codes in TFW-XXXX format', function () {
        $code = DeviceCode::generateCode();

        expect($code)->toMatch('/^TFW-[A-Z2-9]{6}$/');
    });

    it('generates unique codes', function () {
        $codes = collect(range(1, 20))->map(fn () => DeviceCode::generateCode());

        expect($codes->unique()->count())->toBe(20);
    });

    it('identifies expired codes', function () {
        $deviceCode = DeviceCode::factory()->expired()->create();

        expect($deviceCode->isExpired())->toBeTrue();
        expect($deviceCode->isPending())->toBeFalse();
    });

    it('identifies authorized codes', function () {
        $deviceCode = DeviceCode::factory()->authorized()->create();

        expect($deviceCode->isAuthorized())->toBeTrue();
    });

    it('identifies pending codes', function () {
        $deviceCode = DeviceCode::factory()->create();

        expect($deviceCode->isPending())->toBeTrue();
        expect($deviceCode->isExpired())->toBeFalse();
        expect($deviceCode->isAuthorized())->toBeFalse();
    });
});
