<?php

use App\Models\Liveset;
use App\Models\Track;
use App\Models\User;
use App\Services\TrackLinker;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->linker = app(TrackLinker::class);
    $this->liveset = Liveset::factory()->create(['title' => 'Stambak & Co House']);
    $this->liveset->tracks()->create(['title' => 'Bicep - Glue', 'timestamp' => 300, 'order' => 1]);
    $this->track = Track::firstWhere('slug', 'bicep-glue');
});

describe('liking tracks', function () {
    it('likes and unlikes a track, idempotently', function () {
        $this->actingAs($this->user, 'sanctum')->putJson('/api/tracks/bicep-glue/like')->assertOk()->assertJson(['liked' => true]);
        $this->actingAs($this->user, 'sanctum')->putJson('/api/tracks/bicep-glue/like')->assertOk();

        expect($this->user->likedTracks()->count())->toBe(1);

        $this->actingAs($this->user, 'sanctum')->deleteJson('/api/tracks/bicep-glue/like')->assertOk()->assertJson(['liked' => false]);
        $this->actingAs($this->user, 'sanctum')->deleteJson('/api/tracks/bicep-glue/like')->assertOk();

        expect($this->user->likedTracks()->count())->toBe(0);
    });

    it('lists liked tracks, last liked first, with where they were played', function () {
        $other = Track::factory()->create(['artist' => 'Four Tet', 'title' => 'In My Dreams']);
        $this->travelTo(now()->subMinute(), fn () => $this->user->likedTracks()->attach($this->track));
        $this->user->likedTracks()->attach($other);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/track-likes')
            ->assertOk()
            ->assertJsonCount(2, 'tracks')
            ->assertJsonPath('tracks.0.slug', $other->slug)
            ->assertJsonPath('tracks.1.slug', 'bicep-glue')
            ->assertJsonPath('tracks.1.appearances.0.liveset_title', 'Stambak & Co House')
            ->assertJsonPath('tracks.1.appearances.0.timestamp', 300);
    });

    it('needs a login', function () {
        $this->getJson('/api/track-likes')->assertUnauthorized();
        $this->putJson('/api/tracks/bicep-glue/like')->assertUnauthorized();
    });

    it('returns 404 for an unknown track', function () {
        $this->actingAs($this->user, 'sanctum')->putJson('/api/tracks/nope/like')->assertNotFound();
    });
});

describe('likes and fixing links', function () {
    it('moves likes along when a track is merged, without doubles', function () {
        $keep = Track::factory()->create();
        $bothLiked = User::factory()->create();
        $this->user->likedTracks()->attach($this->track);
        $bothLiked->likedTracks()->attach([$this->track->id, $keep->id]);

        $this->linker->merge($this->track, $keep);

        expect($this->user->likedTracks()->pluck('tracks.id')->all())->toBe([$keep->id])
            ->and($bothLiked->likedTracks()->pluck('tracks.id')->all())->toBe([$keep->id]);
    });

    it('keeps the likes when a guessed track is replaced, e.g. by swapping artist and title', function () {
        $alias = $this->linker->link('Gem Lingo - Overmono');
        $wrong = $alias->tracks->first();
        $this->user->likedTracks()->attach($wrong);

        $this->linker->swap($alias);

        $right = $alias->fresh()->tracks->first();
        expect(Track::find($wrong->id))->toBeNull()
            ->and($right->artist)->toBe('Overmono')
            ->and($this->user->likedTracks()->pluck('tracks.id')->all())->toBe([$right->id]);
    });
});
