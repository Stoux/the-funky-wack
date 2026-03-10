<?php

use App\Models\Edition;
use App\Models\Liveset;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->edition = Edition::create([
        'number' => '99',
        'tag_line' => 'Test Edition',
        'date' => now(),
    ]);
});

function actingAsAdminUser($test)
{
    return $test->withSession(['admin_authenticated_at' => now()])->actingAs($test->admin);
}

describe('store liveset with transitions', function () {
    it('stores tracks with transition_start from tilde prefix', function () {
        $response = actingAsAdminUser($this)->post('/admin/livesets', [
            'edition_id' => $this->edition->id,
            'title' => 'Test Liveset',
            'artist_name' => 'Test DJ',
            'tracks_text' => "00:00:00 | Intro\n00:03:45 | Track A\n~00:03:45 00:04:15 | Track B\n00:07:22 | Track C",
        ]);

        $response->assertRedirect();

        $liveset = Liveset::where('title', 'Test Liveset')->first();
        expect($liveset)->not->toBeNull();

        $tracks = $liveset->tracks()->orderBy('order')->get();
        expect($tracks)->toHaveCount(4);

        // Intro — no transition
        expect($tracks[0]->title)->toBe('Intro');
        expect($tracks[0]->timestamp)->toBe(0);
        expect($tracks[0]->transition_start)->toBeNull();

        // Track A — no transition
        expect($tracks[1]->title)->toBe('Track A');
        expect($tracks[1]->timestamp)->toBe(225); // 3*60 + 45
        expect($tracks[1]->transition_start)->toBeNull();

        // Track B — has transition
        expect($tracks[2]->title)->toBe('Track B');
        expect($tracks[2]->timestamp)->toBe(255); // 4*60 + 15
        expect($tracks[2]->transition_start)->toBe(225); // 3*60 + 45

        // Track C — no transition
        expect($tracks[3]->title)->toBe('Track C');
        expect($tracks[3]->timestamp)->toBe(442); // 7*60 + 22
        expect($tracks[3]->transition_start)->toBeNull();
    });

    it('stores tracks without any transitions (backwards compatible)', function () {
        $response = actingAsAdminUser($this)->post('/admin/livesets', [
            'edition_id' => $this->edition->id,
            'title' => 'No Transitions',
            'artist_name' => 'Test DJ',
            'tracks_text' => "00:00:00 | Intro\n00:03:00 | Track A",
        ]);

        $response->assertRedirect();

        $liveset = Liveset::where('title', 'No Transitions')->first();
        $tracks = $liveset->tracks()->orderBy('order')->get();

        expect($tracks)->toHaveCount(2);
        expect($tracks[0]->transition_start)->toBeNull();
        expect($tracks[1]->transition_start)->toBeNull();
    });

    it('stores tracks without timestamps alongside transitions', function () {
        $response = actingAsAdminUser($this)->post('/admin/livesets', [
            'edition_id' => $this->edition->id,
            'title' => 'Mixed Format',
            'artist_name' => 'Test DJ',
            'tracks_text' => "--:--:-- | Unknown Track\n00:03:00 | Track A\n~00:03:00 00:03:30 | Track B",
        ]);

        $response->assertRedirect();

        $liveset = Liveset::where('title', 'Mixed Format')->first();
        $tracks = $liveset->tracks()->orderBy('order')->get();

        expect($tracks)->toHaveCount(3);
        expect($tracks[0]->timestamp)->toBeNull();
        expect($tracks[0]->transition_start)->toBeNull();
        expect($tracks[2]->transition_start)->toBe(180);
    });
});

describe('update liveset with transitions', function () {
    it('updates tracks with transition_start', function () {
        actingAsAdminUser($this)->post('/admin/livesets', [
            'edition_id' => $this->edition->id,
            'title' => 'Update Test',
            'artist_name' => 'Test DJ',
            'tracks_text' => "00:00:00 | Intro\n00:03:00 | Track A",
        ]);

        $liveset = Liveset::where('title', 'Update Test')->first();

        $response = actingAsAdminUser($this)->patch("/admin/livesets/{$liveset->id}", [
            'edition_id' => $this->edition->id,
            'title' => 'Update Test',
            'artist_name' => 'Test DJ',
            'tracks_text' => "00:00:00 | Intro\n00:03:00 | Track A\n~00:03:00 00:03:30 | Track B",
        ]);

        $response->assertRedirect();

        $tracks = $liveset->tracks()->orderBy('order')->get();
        expect($tracks)->toHaveCount(3);
        expect($tracks[2]->transition_start)->toBe(180);
        expect($tracks[2]->timestamp)->toBe(210);
    });
});

describe('transition validation', function () {
    it('rejects blend start >= takes-over timestamp', function () {
        $response = actingAsAdminUser($this)->post('/admin/livesets', [
            'edition_id' => $this->edition->id,
            'title' => 'Invalid Transition',
            'artist_name' => 'Test DJ',
            'tracks_text' => "00:00:00 | Intro\n~00:04:15 00:03:45 | Track B",
        ]);

        $response->assertSessionHasErrors('tracks_text');
    });

    it('rejects blend start equal to takes-over timestamp', function () {
        $response = actingAsAdminUser($this)->post('/admin/livesets', [
            'edition_id' => $this->edition->id,
            'title' => 'Invalid Transition',
            'artist_name' => 'Test DJ',
            'tracks_text' => "00:00:00 | Intro\n~00:03:45 00:03:45 | Track B",
        ]);

        $response->assertSessionHasErrors('tracks_text');
    });

    it('rejects blend start before previous track timestamp', function () {
        $response = actingAsAdminUser($this)->post('/admin/livesets', [
            'edition_id' => $this->edition->id,
            'title' => 'Invalid Transition',
            'artist_name' => 'Test DJ',
            'tracks_text' => "00:00:00 | Intro\n00:05:00 | Track A\n~00:04:00 00:05:30 | Track B",
        ]);

        $response->assertSessionHasErrors('tracks_text');
    });

    it('rejects invalid time components in transition', function () {
        $response = actingAsAdminUser($this)->post('/admin/livesets', [
            'edition_id' => $this->edition->id,
            'title' => 'Invalid Time',
            'artist_name' => 'Test DJ',
            'tracks_text' => "00:00:00 | Intro\n~00:60:00 00:04:15 | Track B",
        ]);

        $response->assertSessionHasErrors('tracks_text');
    });
});

describe('admin UI round-trip', function () {
    it('returns transition_start in liveset tracks data', function () {
        actingAsAdminUser($this)->post('/admin/livesets', [
            'edition_id' => $this->edition->id,
            'title' => 'Round Trip Test',
            'artist_name' => 'Test DJ',
            'tracks_text' => "00:00:00 | Intro\n00:03:45 | Track A\n~00:03:45 00:04:15 | Track B",
        ]);

        $liveset = Liveset::where('title', 'Round Trip Test')->first();

        $response = actingAsAdminUser($this)->get("/admin/livesets/{$liveset->id}");
        $response->assertOk();

        $page = $response->original->getData()['page'];
        $livesetData = $page['props']['liveset'];
        $tracks = $livesetData['tracks'];

        // Track B should have transition_start
        $trackB = collect($tracks)->firstWhere('title', 'Track B');
        expect($trackB['transition_start'])->toBe(225);
        expect($trackB['timestamp'])->toBe(255);

        // Track A should not have transition_start
        $trackA = collect($tracks)->firstWhere('title', 'Track A');
        expect($trackA['transition_start'])->toBeNull();
    });
});
