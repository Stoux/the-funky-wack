<?php

use App\Enums\TrackAliasStatus;
use App\Models\Liveset;
use App\Models\Track;
use App\Models\User;
use App\Services\TrackLinker;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->forceFill(['is_admin' => true])->save();
    $this->linker = app(TrackLinker::class);
});

function asAdmin(): Tests\TestCase
{
    return test()->actingAs(test()->admin)->withSession(['admin_authenticated_at' => now()]);
}

describe('admin tracks page', function () {
    it('lists lines to review with the sets they appear in', function () {
        $liveset = Liveset::factory()->create(['title' => 'Moeilijke UK Shit']);
        $liveset->tracks()->create(['title' => 'Four Tet - In My Dreams', 'timestamp' => 0, 'order' => 1]);
        $liveset->tracks()->create(['title' => '? - Bye, for now', 'timestamp' => 60, 'order' => 2]);

        asAdmin()->get(route('admin.tracks'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Tracks')
                ->where('counts.review', 1)
                ->where('counts.unknown', 1)
                ->has('aliases.data', 1)
                ->where('aliases.data.0.text', 'Four Tet - In My Dreams')
                ->where('aliases.data.0.tracks.0.name', 'Four Tet - In My Dreams')
                ->where('aliases.data.0.livesets.0.title', 'Moeilijke UK Shit'));
    });

    it('shows tracks with the same title as possible duplicates', function () {
        $this->linker->link('Flowdan - Shella Verse');
        $this->linker->link('Flowdan & Sammy Virji - Shella Verse');
        $this->linker->link('Bicep - Glue');

        asAdmin()->get(route('admin.tracks', ['filter' => 'duplicates']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('counts.duplicates', 1)
                ->has('duplicates.0', 2));
    });

    it('is only for admins', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.tracks'))
            ->assertRedirect();
    });

    it('searches tracks for the pickers', function () {
        $this->linker->link('Bicep - Glue');
        $this->linker->link('Four Tet - In My Dreams');

        asAdmin()->getJson(route('admin.tracks.search', ['q' => 'bic']))
            ->assertOk()
            ->assertJsonCount(1, 'tracks')
            ->assertJsonPath('tracks.0.name', 'Bicep - Glue');
    });
});

describe('reviewing a line', function () {
    it('marks an auto link as reviewed', function () {
        $alias = $this->linker->link('Bicep - Glue');

        asAdmin()->post(route('admin.track-aliases.review', $alias))->assertRedirect();

        expect($alias->fresh()->status)->toBe(TrackAliasStatus::Reviewed);
    });

    it('swaps a line written as Title - Artist and drops the wrong track', function () {
        $alias = $this->linker->link('Gem Lingo (Ovr Now) - Overmono');

        asAdmin()->post(route('admin.track-aliases.swap', $alias))->assertRedirect();

        $alias->refresh();
        expect($alias->status)->toBe(TrackAliasStatus::Reviewed)
            ->and($alias->tracks->first())
            ->artist->toBe('Overmono')
            ->title->toBe('Gem Lingo')
            ->version->toBe('Ovr Now')
            ->and(Track::count())->toBe(1);
    });

    it('links a line to several tracks for a mashup, in order', function () {
        $alias = $this->linker->link('Geck-o vs R. Kelly - Ja Toch (Ignition Mash-up)');
        $first = Track::factory()->create(['artist' => 'Geck-o', 'title' => 'Ja Toch']);
        $second = Track::factory()->create(['artist' => 'R. Kelly', 'title' => 'Ignition']);

        asAdmin()->put(route('admin.track-aliases.link', $alias), ['track_ids' => [$second->id, $first->id]])
            ->assertRedirect();

        expect($alias->fresh()->tracks->pluck('id')->all())->toBe([$second->id, $first->id])
            ->and(Track::count())->toBe(2);
    });

    it('links a line to a new track', function () {
        $alias = $this->linker->link('01 I Feel Love');

        asAdmin()->put(route('admin.track-aliases.link', $alias), [
            'track_ids' => [],
            'new_track' => ['artist' => 'Donna Summer', 'title' => 'I Feel Love', 'version' => null],
        ])->assertRedirect();

        expect($alias->fresh()->tracks->first()->displayName())->toBe('Donna Summer - I Feel Love')
            ->and($alias->fresh()->status)->toBe(TrackAliasStatus::Reviewed);
    });

    it('validates the link input', function (array $payload, string $error) {
        $alias = $this->linker->link('Bicep - Glue');

        asAdmin()->put(route('admin.track-aliases.link', $alias), $payload)
            ->assertSessionHasErrors($error);
    })->with([
        'missing track ids' => [[], 'track_ids'],
        'unknown track' => [['track_ids' => [999]], 'track_ids.0'],
        'new track without title' => [['track_ids' => [], 'new_track' => ['artist' => 'Bicep']], 'new_track.title'],
        'too many tracks' => [['track_ids' => [1, 2, 3, 4, 5, 6]], 'track_ids'],
    ]);

    it('marks a line as unknown', function () {
        $alias = $this->linker->link('Lionsworth - Hello World');

        asAdmin()->post(route('admin.track-aliases.unknown', $alias))->assertRedirect();

        expect($alias->fresh()->tracks)->toBeEmpty()
            ->and($alias->fresh()->status)->toBe(TrackAliasStatus::Reviewed)
            ->and(Track::count())->toBe(0);
    });
});

describe('merging tracks', function () {
    it('moves every line to the target and removes the source', function () {
        $keep = $this->linker->link('Flowdan, Sammy Virji - Shella Verse')->tracks->first();
        $mashup = $this->linker->link('Shella Verse vs Glue');
        $dupeAlias = $this->linker->link('Flowdan - Shella Verse');
        $dupe = $dupeAlias->tracks->first();
        $glue = Track::factory()->create(['artist' => 'Bicep', 'title' => 'Glue']);
        $this->linker->relink($mashup, [$dupe->id, $glue->id]);

        asAdmin()->post(route('admin.tracks.merge', $dupe), ['target_id' => $keep->id])->assertRedirect();

        expect(Track::find($dupe->id))->toBeNull()
            ->and($dupeAlias->fresh()->tracks->pluck('id')->all())->toBe([$keep->id])
            ->and($mashup->fresh()->tracks->pluck('id')->all())->toBe([$keep->id, $glue->id]);
    });

    it('does not merge a track into itself', function () {
        $track = Track::factory()->create();

        asAdmin()->post(route('admin.tracks.merge', $track), ['target_id' => $track->id])
            ->assertSessionHasErrors('target_id');
    });
});

describe('liveset editor', function () {
    it('shows the track links of the liveset', function () {
        $liveset = Liveset::factory()->create();
        $liveset->tracks()->create(['title' => 'Four Tet - In My Dreams', 'timestamp' => 0, 'order' => 1]);
        $liveset->tracks()->create(['title' => 'Four Tet - In My Dreams', 'timestamp' => 300, 'order' => 2]);

        asAdmin()->get(route('admin.livesets.view', $liveset))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('trackAliases', 1)
                ->where('trackAliases.0.tracks.0.name', 'Four Tet - In My Dreams')
                ->missing('liveset.tracks.0.alias'));
    });
});
