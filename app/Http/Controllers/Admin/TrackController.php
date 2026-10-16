<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TrackAliasStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\LinkTrackAliasRequest;
use App\Http\Requests\MergeTrackRequest;
use App\Models\Track;
use App\Models\TrackAlias;
use App\Services\TrackLinker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TrackController extends Controller
{
    private const FILTERS = ['review', 'unknown', 'reviewed', 'tracks', 'duplicates'];

    public function __construct(public TrackLinker $linker) {}

    public function tracks(Request $request): Response
    {
        $filter = in_array($request->query('filter'), self::FILTERS, true) ? $request->query('filter') : 'review';
        $search = trim((string) $request->query('q', ''));

        return Inertia::render('Admin/Tracks', [
            'filter' => $filter,
            'search' => $search,
            'counts' => [
                'review' => TrackAlias::where('status', TrackAliasStatus::Auto)->count(),
                'unknown' => TrackAlias::where('status', TrackAliasStatus::Unknown)->count(),
                'reviewed' => TrackAlias::where('status', TrackAliasStatus::Reviewed)->count(),
                'tracks' => Track::count(),
                'duplicates' => $this->duplicateGroups()->count(),
            ],
            'aliases' => in_array($filter, ['review', 'unknown', 'reviewed'], true) ? $this->aliases($filter, $search) : null,
            'tracks' => $filter === 'tracks' ? $this->trackList($search) : null,
            'duplicates' => $filter === 'duplicates' ? $this->duplicateGroups()->take(50)->values() : null,
        ]);
    }

    /**
     * Tracks for the link and merge pickers
     */
    public function search(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        $tracks = Track::query()
            ->withCount('aliases')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('artist', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")))
            ->orderBy('artist')
            ->orderBy('title')
            ->limit(20)
            ->get()
            ->map(fn (Track $track) => self::formatTrack($track));

        return response()->json(['tracks' => $tracks]);
    }

    public function review(TrackAlias $alias): RedirectResponse
    {
        $this->linker->review($alias);

        return back()->with('success', 'Marked as reviewed.');
    }

    public function swap(TrackAlias $alias): RedirectResponse
    {
        $this->linker->swap($alias);

        return back()->with('success', 'Artist and title swapped.');
    }

    public function unknown(TrackAlias $alias): RedirectResponse
    {
        $this->linker->markUnknown($alias);

        return back()->with('success', 'Marked as unknown.');
    }

    public function link(LinkTrackAliasRequest $request, TrackAlias $alias): RedirectResponse
    {
        $trackIds = $request->validated('track_ids');

        if ($newTrack = $request->validated('new_track')) {
            $trackIds[] = $this->linker->findOrCreateTrack($newTrack['artist'], $newTrack['title'], $newTrack['version'] ?? null)->id;
        }

        $this->linker->relink($alias, $trackIds);

        return back()->with('success', 'Line linked.');
    }

    public function merge(MergeTrackRequest $request, Track $track): RedirectResponse
    {
        $this->linker->merge($track, Track::findOrFail($request->validated('target_id')));

        return back()->with('success', 'Tracks merged.');
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatTrack(Track $track): array
    {
        return [
            'id' => $track->id,
            'artist' => $track->artist,
            'title' => $track->title,
            'version' => $track->version,
            'slug' => $track->slug,
            'name' => $track->displayName(),
            // How many tracklist lines use this song, 1 means it was made from (or only fits) this line
            'lines_count' => $track->aliases_count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatAlias(TrackAlias $alias): array
    {
        return [
            'id' => $alias->id,
            'text' => $alias->text,
            'status' => $alias->status->value,
            'tracks' => $alias->tracks->map(fn (Track $track) => self::formatTrack($track))->values(),
            'livesets' => $alias->relationLoaded('livesetTracks')
                ? $alias->livesetTracks->pluck('liveset')->filter()->unique('id')->map(fn ($liveset) => [
                    'id' => $liveset->id,
                    'title' => $liveset->title,
                ])->values()
                : [],
        ];
    }

    private function aliases(string $filter, string $search): mixed
    {
        $status = match ($filter) {
            'unknown' => TrackAliasStatus::Unknown,
            'reviewed' => TrackAliasStatus::Reviewed,
            default => TrackAliasStatus::Auto,
        };

        return TrackAlias::query()
            ->where('status', $status)
            ->when($search !== '', fn ($query) => $query->where('text', 'like', "%{$search}%"))
            ->with(['tracks' => fn ($query) => $query->withCount('aliases'), 'livesetTracks.liveset:id,title'])
            ->orderBy('text')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (TrackAlias $alias) => self::formatAlias($alias));
    }

    private function trackList(string $search): mixed
    {
        return Track::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('artist', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")))
            ->withCount('aliases')
            ->with('aliases.livesetTracks.liveset:id,title')
            ->orderBy('artist')
            ->orderBy('title')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Track $track) => [
                ...self::formatTrack($track),
                'lines' => $track->aliases->map(fn (TrackAlias $alias) => [
                    'id' => $alias->id,
                    'text' => $alias->text,
                    'livesets' => $alias->livesetTracks->pluck('liveset')->filter()->unique('id')->pluck('title')->values(),
                ])->values(),
            ]);
    }

    /**
     * Tracks with the same title and version but a different artist spelling, likely the same song
     *
     * @return Collection<int, array<int, array<string, mixed>>>
     */
    private function duplicateGroups(): Collection
    {
        return Track::query()
            ->withCount('aliases')
            ->get()
            ->groupBy(fn (Track $track) => explode('|', $track->match_key, 2)[1] ?? '')
            ->filter(fn (Collection $group, string $key) => $group->count() > 1 && $key !== '|')
            ->map(fn (Collection $group) => $group->map(fn (Track $track) => self::formatTrack($track))->values()->all())
            ->values();
    }
}
