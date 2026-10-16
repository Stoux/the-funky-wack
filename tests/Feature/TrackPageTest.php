<?php

use App\Models\Edition;
use App\Models\Liveset;
use App\Services\EditionsDataService;
use App\Services\TimetablerService;
use App\Services\TrackLinker;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->linker = app(TrackLinker::class);
    $this->older = Liveset::factory()->for(Edition::factory()->state(['date' => '2025-01-01']))->create(['title' => 'Older set']);
    $this->newer = Liveset::factory()->for(Edition::factory()->state(['date' => '2025-06-01']))->create(['title' => 'Newer set']);
});

describe('tracks overview', function () {
    it('lists tracks with the number of sets, most played first', function () {
        $this->older->tracks()->create(['title' => 'Bicep - Glue', 'timestamp' => 0, 'order' => 1]);
        $this->newer->tracks()->create(['title' => 'Bicep - Glue (Original Mix)', 'timestamp' => 60, 'order' => 1]);
        $this->newer->tracks()->create(['title' => 'Four Tet - In My Dreams', 'timestamp' => 120, 'order' => 2]);

        $this->get(route('tracks.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Tracks/Index')
                ->has('tracks.data', 2)
                ->where('tracks.data.0.title', 'Glue')
                ->where('tracks.data.0.sets_count', 2)
                ->where('tracks.data.1.sets_count', 1));
    });

    it('leaves out tracks that are not in any set anymore', function () {
        $this->older->tracks()->create(['title' => 'Bicep - Glue', 'timestamp' => 0, 'order' => 1]);
        App\Models\Track::factory()->create(['artist' => 'Liked', 'title' => 'But gone']);

        $this->get(route('tracks.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('tracks.data', 1)
                ->where('tracks.data.0.title', 'Glue'));
    });

    it('searches and sorts by name', function () {
        $this->older->tracks()->create(['title' => 'Four Tet - In My Dreams', 'timestamp' => 0, 'order' => 1]);
        $this->older->tracks()->create(['title' => 'Bicep - Glue', 'timestamp' => 60, 'order' => 2]);
        $this->newer->tracks()->create(['title' => 'Four Tet - In My Dreams', 'timestamp' => 0, 'order' => 1]);

        $this->get(route('tracks.index', ['sort' => 'name']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('tracks.data.0.artist', 'Bicep'));

        $this->get(route('tracks.index', ['q' => 'dream']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('tracks.data', 1)
                ->where('tracks.data.0.artist', 'Four Tet'));
    });
});

describe('track page', function () {
    it('shows every set the track was played in, newest edition first, with the timestamp', function () {
        $this->older->tracks()->create(['title' => 'Bicep - Glue', 'timestamp' => 300, 'order' => 1]);
        $this->newer->tracks()->create(['title' => 'BICEP - Glue (Original Mix)', 'timestamp' => 1210, 'order' => 1]);
        $slug = $this->linker->link('Bicep - Glue')->tracks->first()->slug;

        $this->get(route('tracks.show', $slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Tracks/Show')
                ->where('track.title', 'Glue')
                ->has('appearances', 2)
                ->where('appearances.0.liveset_title', 'Newer set')
                ->where('appearances.0.timestamp', 1210)
                ->where('appearances.1.liveset_title', 'Older set'));
    });

    it('links the other versions of the same track', function () {
        $this->older->tracks()->create(['title' => 'Bicep - Glue', 'timestamp' => 0, 'order' => 1]);
        $this->older->tracks()->create(['title' => 'Bicep - Glue (Hammer Remix)', 'timestamp' => 60, 'order' => 2]);
        $this->older->tracks()->create(['title' => 'Bicep - Glue 2', 'timestamp' => 120, 'order' => 3]);

        $this->get(route('tracks.show', 'bicep-glue'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('otherVersions', 1)
                ->where('otherVersions.0.version', 'Hammer Remix'));
    });

    it('returns 404 for an unknown track', function () {
        $this->get(route('tracks.show', 'does-not-exist'))->assertNotFound();
    });
});

describe('editions data', function () {
    it('gives every tracklist line its songs', function () {
        EditionsDataService::clearCache();
        $this->older->tracks()->create(['title' => 'Bicep - Glue', 'timestamp' => 0, 'order' => 1]);
        $this->older->tracks()->create(['title' => '? - Unknown banger', 'timestamp' => 60, 'order' => 2]);

        $tracks = collect(app(EditionsDataService::class)->buildEditionsData(app(TimetablerService::class)))
            ->flatMap(fn (array $edition) => $edition['livesets'])
            ->firstWhere('id', $this->older->id)['tracks'];

        expect($tracks[0]['songs'])->toBe([['slug' => 'bicep-glue', 'artist' => 'Bicep', 'title' => 'Glue', 'version' => null]])
            ->and($tracks[1]['songs'])->toBe([])
            ->and($tracks[0])->not->toHaveKey('alias');
    });

    it('drops the cached editions data when a line is relinked', function () {
        $this->older->tracks()->create(['title' => 'Bicep - Glue', 'timestamp' => 0, 'order' => 1]);
        app(EditionsDataService::class)->buildEditionsData(app(TimetablerService::class));
        $alias = $this->linker->link('Bicep - Glue');

        $this->linker->markUnknown($alias);

        $tracks = collect(app(EditionsDataService::class)->buildEditionsData(app(TimetablerService::class)))
            ->flatMap(fn (array $edition) => $edition['livesets'])
            ->firstWhere('id', $this->older->id)['tracks'];
        expect($tracks[0]['songs'])->toBe([]);
    });
});
