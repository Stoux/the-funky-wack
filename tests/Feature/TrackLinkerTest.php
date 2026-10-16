<?php

use App\Enums\TrackAliasStatus;
use App\Models\Liveset;
use App\Models\Track;
use App\Models\TrackAlias;
use App\Services\TrackLinker;

beforeEach(function () {
    $this->linker = app(TrackLinker::class);
});

describe('parsing a tracklist line', function () {
    it('splits artist, title and version', function (string $line, array $expected) {
        expect($this->linker->parse($line))->toBe($expected);
    })->with([
        'plain' => ['Four Tet - In My Dreams', ['artist' => 'Four Tet', 'title' => 'In My Dreams', 'version' => null]],
        'remix is a version' => ['For Those I Love - I Have a Love (Overmono Remix)', ['artist' => 'For Those I Love', 'title' => 'I Have a Love', 'version' => 'Overmono Remix']],
        'mix noise is dropped' => ['Joy Orbison - flight fm (Original Mix)', ['artist' => 'Joy Orbison', 'title' => 'flight fm', 'version' => null]],
        'noise and a version' => ['Lionsworth - Hello World (VIP) [Unreleased]', ['artist' => 'Lionsworth', 'title' => 'Hello World', 'version' => 'VIP']],
        'en dash' => ['Ivy Lab – Precious', ['artist' => 'Ivy Lab', 'title' => 'Precious', 'version' => null]],
        'dash inside the title' => ['Flowdan - Shella Verse - Part 2', ['artist' => 'Flowdan', 'title' => 'Shella Verse - Part 2', 'version' => null]],
    ]);

    it('treats unknown tracks as unparseable', function (string $line) {
        expect($this->linker->parse($line))->toBeNull();
    })->with([
        'question mark artist' => ["? - Don't U Run MayMaster"],
        'ID - ID' => ['ID - ID'],
        'no artist' => ['Intro'],
        'unknown title' => ['Bicep - ?'],
    ]);

    it('ignores case, accents, punctuation and artist order when matching', function () {
        $key = fn (string $artist, string $title) => $this->linker->matchKey($artist, $title, null);

        expect($key('Flowdan, Sammy Virji', 'Shella Verse'))
            ->toBe($key('Sammy Virji & Flowdan', 'shella verse'))
            ->toBe($key('Sammy Virji x Flowdan', 'Shella-Verse!'))
            ->and($key('Röyksopp', 'Eple'))->toBe($key('Royksopp', 'Eple'))
            ->and($key('Bicep', 'Glue'))->not->toBe($this->linker->matchKey('Bicep', 'Glue', 'Hammer Remix'));
    });
});

describe('linking lines to tracks', function () {
    it('creates a track the first time a line is seen', function () {
        $alias = $this->linker->link('For Those I Love - I Have a Love (Overmono Remix)');

        expect($alias->status)->toBe(TrackAliasStatus::Auto)
            ->and($alias->tracks)->toHaveCount(1)
            ->and($alias->tracks->first())
            ->artist->toBe('For Those I Love')
            ->title->toBe('I Have a Love')
            ->version->toBe('Overmono Remix')
            ->slug->toBe('for-those-i-love-i-have-a-love-overmono-remix');
    });

    it('links another spelling of the same track to it', function () {
        $first = $this->linker->link('Flowdan, Sammy Virji - Shella Verse (Extended Mix)');
        $second = $this->linker->link('Sammy Virji & Flowdan - Shella Verse');

        expect(Track::count())->toBe(1)
            ->and($second->tracks->first()->id)->toBe($first->tracks->first()->id);
    });

    it('keeps a remix as its own track', function () {
        $this->linker->link('Bicep - Glue');
        $this->linker->link('Bicep - Glue (Hammer Remix)');

        expect(Track::count())->toBe(2);
    });

    it('marks unknown lines without linking a track', function () {
        $alias = $this->linker->link('? - Bye, for now');

        expect($alias->status)->toBe(TrackAliasStatus::Unknown)
            ->and($alias->tracks)->toBeEmpty()
            ->and(Track::count())->toBe(0);
    });

    it('leaves an existing alias alone, so admin fixes stick', function () {
        $track = Track::factory()->create();
        $alias = TrackAlias::factory()->reviewed()->create(['text' => 'Gem Lingo (Ovr Now) - Overmono,Ruthven 134']);
        $alias->tracks()->attach($track, ['position' => 0]);

        $linked = $this->linker->link('Gem Lingo (Ovr Now) - Overmono,Ruthven 134');

        expect($linked->id)->toBe($alias->id)
            ->and($linked->status)->toBe(TrackAliasStatus::Reviewed)
            ->and($linked->tracks->pluck('id')->all())->toBe([$track->id])
            ->and(Track::count())->toBe(1);
    });

    it('gives tracks with the same name but a different key a unique slug', function () {
        Track::factory()->create(['artist' => 'Bicep', 'title' => 'Glue', 'slug' => 'bicep-glue', 'match_key' => 'other']);

        expect($this->linker->link('Bicep - Glue')->tracks->first()->slug)->toBe('bicep-glue-2');
    });
});

describe('tracklists', function () {
    it('links lines when a tracklist is saved, and keeps the links when it is saved again', function () {
        $liveset = Liveset::factory()->create();
        $liveset->tracks()->create(['title' => 'Four Tet - In My Dreams', 'timestamp' => 0, 'order' => 1]);

        $alias = TrackAlias::firstWhere('text', 'Four Tet - In My Dreams');
        $alias->update(['status' => TrackAliasStatus::Reviewed]);

        // The admin saves the tracklist again: all rows are recreated
        $liveset->tracks()->delete();
        $line = $liveset->tracks()->create(['title' => 'Four Tet - In My Dreams', 'timestamp' => 0, 'order' => 1]);

        expect($line->alias->id)->toBe($alias->id)
            ->and($line->alias->status)->toBe(TrackAliasStatus::Reviewed)
            ->and(TrackAlias::count())->toBe(1);
    });

    it('backfills existing tracklists with the tracks:link command', function () {
        $liveset = Liveset::factory()->create();
        $liveset->tracks()->createQuietly(['title' => 'Ivy Lab - Precious', 'timestamp' => 0, 'order' => 1]);
        $liveset->tracks()->createQuietly(['title' => 'Intro', 'timestamp' => 60, 'order' => 2]);

        $this->artisan('tracks:link')->assertSuccessful();
        $this->artisan('tracks:link')->assertSuccessful();

        expect(TrackAlias::count())->toBe(2)
            ->and(Track::count())->toBe(1)
            ->and(TrackAlias::firstWhere('text', 'Intro')->status)->toBe(TrackAliasStatus::Unknown);
    });
});
