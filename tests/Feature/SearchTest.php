<?php

use App\Models\Edition;
use App\Models\Liveset;
use App\Models\User;

beforeEach(function () {
    $edition = Edition::factory()->create(['number' => '8', 'date' => '2025-05-16']);
    $this->liveset = Liveset::factory()->for($edition)->create([
        'title' => 'Stambak & Co House',
        'artist_name' => 'Stambak',
        'genre' => 'house',
    ]);
    $this->liveset->tracks()->create(['title' => 'Four Tet - In My Dreams', 'timestamp' => 151, 'order' => 1]);
    $this->liveset->tracks()->create(['title' => "? - Don't U Run MayMaster", 'timestamp' => 1354, 'order' => 2]);
});

describe('GET /api/search', function () {
    it('finds sets by title, artist and genre', function () {
        $this->getJson('/api/search?q=stambak')
            ->assertOk()
            ->assertJsonCount(1, 'livesets')
            ->assertJsonPath('livesets.0.title', 'Stambak & Co House')
            ->assertJsonPath('livesets.0.edition.number', '8');
    });

    it('finds tracks with the sets they were played in', function () {
        $this->getJson('/api/search?q=dreams')
            ->assertOk()
            ->assertJsonCount(0, 'livesets')
            ->assertJsonCount(1, 'tracks')
            ->assertJsonPath('tracks.0.slug', 'four-tet-in-my-dreams')
            ->assertJsonPath('tracks.0.appearances.0.liveset_title', 'Stambak & Co House')
            ->assertJsonPath('tracks.0.appearances.0.timestamp', 151);
    });

    it('finds tracklist lines that are not linked to a track', function () {
        $this->getJson('/api/search?q=maymaster')
            ->assertOk()
            ->assertJsonCount(0, 'tracks')
            ->assertJsonCount(1, 'unlinked_lines')
            ->assertJsonPath('unlinked_lines.0.line', "? - Don't U Run MayMaster")
            ->assertJsonPath('unlinked_lines.0.timestamp', 1354)
            ->assertJsonPath('unlinked_lines.0.edition_number', '8');
    });

    it('does not return linked lines as unlinked', function () {
        $this->getJson('/api/search?q=four tet')->assertJsonCount(0, 'unlinked_lines');
    });

    it('leaves soft deleted sets out', function () {
        $this->liveset->delete();

        $this->getJson('/api/search?q=stambak')->assertJsonCount(0, 'livesets');
    });

    it('validates the query', function (string $query, string $error) {
        $this->getJson("/api/search?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors($error);
    })->with([
        'missing' => ['', 'q'],
        'too short' => ['q=a', 'q'],
        'limit too high' => ['q=house&limit=26', 'limit'],
    ]);
});

describe('GET /api/tracks/{slug}', function () {
    it('returns the track with its sets, other versions and whether you like it', function () {
        $this->liveset->tracks()->create(['title' => 'Four Tet - In My Dreams (Ben UFO Edit)', 'timestamp' => 2000, 'order' => 3]);
        $user = User::factory()->create();
        $user->likedTracks()->attach(App\Models\Track::firstWhere('slug', 'four-tet-in-my-dreams'));

        $this->getJson('/api/tracks/four-tet-in-my-dreams')->assertJsonPath('track.is_liked', false);

        $this->actingAs($user, 'sanctum')->getJson('/api/tracks/four-tet-in-my-dreams')
            ->assertOk()
            ->assertJsonPath('track.title', 'In My Dreams')
            ->assertJsonPath('track.is_liked', true)
            ->assertJsonPath('appearances.0.timestamp', 151)
            ->assertJsonPath('other_versions.0.version', 'Ben UFO Edit');
    });

    it('returns 404 for an unknown track', function () {
        $this->getJson('/api/tracks/nope')->assertNotFound();
    });
});
