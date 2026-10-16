<?php

namespace App\Http\Controllers;

use App\Models\LivesetTrack;
use App\Models\Track;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrackController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $sort = $request->query('sort') === 'name' ? 'name' : 'sets';

        $tracks = Track::query()
            ->addSelect(['sets_count' => $this->setsCountQuery()])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('artist', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")))
            ->when($sort === 'sets', fn (Builder $query) => $query->orderByDesc('sets_count'))
            ->orderBy('artist')
            ->orderBy('title')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Track $track) => [
                ...$track->toSummary(),
                'sets_count' => (int) $track->sets_count,
            ]);

        return Inertia::render('Tracks/Index', [
            'tracks' => $tracks,
            'search' => $search,
            'sort' => $sort,
        ]);
    }

    public function show(Track $track): Response
    {
        return Inertia::render('Tracks/Show', [
            'track' => $track->toSummary(),
            'appearances' => $track->appearances(),
            'otherVersions' => $track->otherVersions()->map(fn (Track $version) => $version->toSummary()),
        ]);
    }

    /**
     * In how many sets a track was played, through the tracklist lines that link to it
     */
    private function setsCountQuery(): Builder
    {
        return LivesetTrack::query()
            ->selectRaw('count(distinct liveset_tracks.liveset_id)')
            ->join('track_aliases', 'track_aliases.text', '=', 'liveset_tracks.title')
            ->join('track_alias_track', 'track_alias_track.track_alias_id', '=', 'track_aliases.id')
            ->whereColumn('track_alias_track.track_id', 'tracks.id');
    }
}
