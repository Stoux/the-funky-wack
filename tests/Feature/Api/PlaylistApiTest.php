<?php

use App\Models\Playlist;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

describe('GET /api/playlists', function () {
    it('returns public playlists for guests', function () {
        $publicPlaylist = $this->otherUser->playlists()->create([
            'name' => 'Public Playlist',
            'visibility' => 'public',
        ]);

        $privatePlaylist = $this->otherUser->playlists()->create([
            'name' => 'Private Playlist',
            'visibility' => 'private',
        ]);

        $response = $this->getJson('/api/playlists');

        $response->assertOk();
        $response->assertJsonCount(0, 'playlists');
        $response->assertJsonCount(1, 'publicPlaylists');
        $response->assertJsonPath('publicPlaylists.0.name', 'Public Playlist');
    });

    it('returns own playlists and public playlists for authenticated users', function () {
        $myPlaylist = $this->user->playlists()->create([
            'name' => 'My Playlist',
            'visibility' => 'private',
        ]);

        $publicPlaylist = $this->otherUser->playlists()->create([
            'name' => 'Public Playlist',
            'visibility' => 'public',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/playlists');

        $response->assertOk();
        $response->assertJsonCount(1, 'playlists');
        $response->assertJsonPath('playlists.0.name', 'My Playlist');
        $response->assertJsonCount(1, 'publicPlaylists');
        $response->assertJsonPath('publicPlaylists.0.name', 'Public Playlist');
    });
});

describe('GET /api/playlists/{shareCode}', function () {
    it('returns a public playlist for guests', function () {
        $playlist = $this->user->playlists()->create([
            'name' => 'Public Playlist',
            'visibility' => 'public',
        ]);

        $response = $this->getJson("/api/playlists/{$playlist->share_code}");

        $response->assertOk();
        $response->assertJsonPath('playlist.name', 'Public Playlist');
    });

    it('returns an unlisted playlist for guests', function () {
        $playlist = $this->user->playlists()->create([
            'name' => 'Unlisted Playlist',
            'visibility' => 'unlisted',
        ]);

        $response = $this->getJson("/api/playlists/{$playlist->share_code}");

        $response->assertOk();
        $response->assertJsonPath('playlist.name', 'Unlisted Playlist');
    });

    it('returns 403 for a private playlist accessed by guests', function () {
        $playlist = $this->user->playlists()->create([
            'name' => 'Private Playlist',
            'visibility' => 'private',
        ]);

        $response = $this->getJson("/api/playlists/{$playlist->share_code}");

        $response->assertForbidden();
    });

    it('returns 404 for a non-existent share code', function () {
        $response = $this->getJson('/api/playlists/nonexistent');

        $response->assertNotFound();
    });
});

describe('API auth returns JSON errors', function () {
    it('returns 401 JSON for unauthenticated requests to protected endpoints', function () {
        $response = $this->getJson('/api/favorites');

        $response->assertUnauthorized();
        $response->assertJson(['message' => 'Unauthenticated.']);
    });

    it('returns 401 JSON for unauthenticated POST to protected endpoints', function () {
        $response = $this->postJson('/api/playlists', [
            'name' => 'Test',
        ]);

        $response->assertUnauthorized();
        $response->assertJson(['message' => 'Unauthenticated.']);
    });
});
