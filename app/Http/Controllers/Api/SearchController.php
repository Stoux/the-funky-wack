<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Models\Liveset;
use App\Models\LivesetTrack;
use App\Models\Track;
use App\Models\TrackAlias;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    /**
     * Search sets and tracks (Meilisearch through Scout), plus tracklist lines that aren't linked to a track
     */
    public function __invoke(SearchRequest $request): JsonResponse
    {
        $query = $request->validated('q');
        $limit = $request->limit();

        $livesets = Liveset::search($query)
            ->take($limit)
            ->query(fn ($builder) => $builder->with('edition:id,number,tag_line'))
            ->get()
            ->map(fn (Liveset $liveset) => [
                'id' => $liveset->id,
                'title' => $liveset->title,
                'artist_name' => $liveset->artist_name,
                'genre' => $liveset->genre,
                'edition' => $liveset->edition ? [
                    'id' => $liveset->edition->id,
                    'number' => $liveset->edition->number,
                    'tag_line' => $liveset->edition->tag_line,
                ] : null,
            ])
            ->values();

        $tracks = Track::search($query)
            ->take($limit)
            ->get()
            ->map(fn (Track $track) => [
                ...$track->toSummary(),
                'appearances' => $track->appearances(),
            ])
            ->values();

        return response()->json([
            'livesets' => $livesets,
            'tracks' => $tracks,
            'unlinked_lines' => $this->unlinkedLines($query, $limit),
        ]);
    }

    /**
     * Lines without a track ("? - Some ID") aren't in the index, but people still look for them
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function unlinkedLines(string $query, int $limit): \Illuminate\Support\Collection
    {
        $escaped = addcslashes($query, '%_\\');

        return LivesetTrack::query()
            ->where('title', 'like', "%{$escaped}%")
            ->whereNotIn('title', TrackAlias::query()->whereHas('tracks')->select('text'))
            ->with('liveset.edition:id,number')
            ->limit($limit)
            ->get()
            ->filter(fn (LivesetTrack $line) => $line->liveset)
            ->map(fn (LivesetTrack $line) => [
                'line' => $line->title,
                'liveset_id' => $line->liveset->id,
                'liveset_title' => $line->liveset->title,
                'artist_name' => $line->liveset->artist_name,
                'edition_number' => $line->liveset->edition?->number,
                'timestamp' => $line->timestamp,
            ])
            ->values();
    }
}
