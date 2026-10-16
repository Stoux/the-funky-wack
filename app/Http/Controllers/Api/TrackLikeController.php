<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackLikeController extends Controller
{
    /**
     * The user's liked tracks, last liked first, with the sets they were played in
     */
    public function index(Request $request): JsonResponse
    {
        $liked = $request->user()
            ->likedTracks()
            ->orderByPivot('created_at', 'desc')
            ->get();
        $appearances = Track::appearancesFor($liked);
        $tracks = $liked->map(fn (Track $track) => [
            ...$track->toSummary(),
            'liked_at' => $track->pivot->created_at->toIso8601String(),
            'appearances' => $appearances->get($track->id, collect()),
        ]);

        return response()->json(['tracks' => $tracks]);
    }

    /**
     * Like a track. Liking it again changes nothing.
     */
    public function store(Request $request, Track $track): JsonResponse
    {
        $request->user()->likedTracks()->syncWithoutDetaching([$track->id]);

        return response()->json(['liked' => true]);
    }

    public function destroy(Request $request, Track $track): JsonResponse
    {
        $request->user()->likedTracks()->detach($track->id);

        return response()->json(['liked' => false]);
    }
}
