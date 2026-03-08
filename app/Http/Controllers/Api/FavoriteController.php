<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Liveset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    /**
     * List the user's favorites.
     */
    public function index(Request $request): JsonResponse
    {
        $favorites = $request->user()
            ->favoriteLivesets()
            ->with(['edition:id,number,tag_line,date'])
            ->orderByPivot('created_at', 'desc')
            ->get()
            ->map(fn (Liveset $liveset) => [
                'id' => $liveset->id,
                'title' => $liveset->title,
                'artist_name' => $liveset->artist_name,
                'edition' => $liveset->edition ? [
                    'id' => $liveset->edition->id,
                    'number' => $liveset->edition->number,
                    'tag_line' => $liveset->edition->tag_line,
                ] : null,
                'duration_in_seconds' => $liveset->duration_in_seconds,
                'favorited_at' => $liveset->pivot->created_at->toIso8601String(),
            ]);

        return response()->json([
            'favorites' => $favorites,
        ]);
    }

    /**
     * Add a liveset to favorites.
     */
    public function store(Request $request, Liveset $liveset): JsonResponse
    {
        $user = $request->user();

        $result = DB::transaction(function () use ($user, $liveset) {
            // Check inside transaction to prevent race conditions
            if ($user->favoriteLivesets()->where('liveset_id', $liveset->id)->lockForUpdate()->exists()) {
                return null;
            }

            $user->favoriteLivesets()->attach($liveset->id);
            $liveset->increment('favorites_count');

            return $liveset->fresh()->favorites_count;
        });

        if ($result === null) {
            return response()->json([
                'message' => 'Already favorited.',
            ], 409);
        }

        return response()->json([
            'message' => 'Added to favorites.',
            'favorites_count' => $result,
        ], 201);
    }

    /**
     * Remove a liveset from favorites.
     */
    public function destroy(Request $request, Liveset $liveset): JsonResponse
    {
        $user = $request->user();

        $detached = DB::transaction(function () use ($user, $liveset) {
            $count = $user->favoriteLivesets()->detach($liveset->id);
            if ($count > 0) {
                $liveset->decrement('favorites_count');
            }

            return $count;
        });

        if ($detached === 0) {
            return response()->json([
                'message' => 'Not in favorites.',
            ], 404);
        }

        return response()->json([
            'message' => 'Removed from favorites.',
            'favorites_count' => $liveset->fresh()->favorites_count,
        ]);
    }
}
