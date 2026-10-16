<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackController extends Controller
{
    /**
     * A track with the sets it was played in and its other versions, like the web track page
     */
    public function show(Request $request, Track $track): JsonResponse
    {
        $user = $request->user('sanctum');

        return response()->json([
            'track' => [
                ...$track->toSummary(),
                'is_liked' => $user ? $user->likedTracks()->whereKey($track->id)->exists() : false,
            ],
            'appearances' => $track->appearances(),
            'other_versions' => $track->otherVersions()->map(fn (Track $version) => $version->toSummary())->values(),
        ]);
    }
}
