<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Liveset;
use App\Models\Playlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlaylistController extends Controller
{
    /**
     * List playlists. Authenticated users get their own playlists + public ones.
     * Guests get only public playlists.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $publicPlaylistsQuery = Playlist::query()
            ->where('visibility', 'public')
            ->with('user:id,name')
            ->withCount('items')
            ->orderByDesc('updated_at')
            ->limit(50);

        if ($user) {
            $myPlaylists = $user
                ->playlists()
                ->withCount('items')
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (Playlist $playlist) => $this->formatPlaylist($playlist, true));

            $publicPlaylists = $publicPlaylistsQuery
                ->where('user_id', '!=', $user->id)
                ->get()
                ->map(fn (Playlist $playlist) => $this->formatPlaylist($playlist, false));

            return response()->json([
                'playlists' => $myPlaylists,
                'publicPlaylists' => $publicPlaylists,
            ]);
        }

        $publicPlaylists = $publicPlaylistsQuery
            ->get()
            ->map(fn (Playlist $playlist) => $this->formatPlaylist($playlist, false));

        return response()->json([
            'playlists' => [],
            'publicPlaylists' => $publicPlaylists,
        ]);
    }

    /**
     * Format a playlist for API response.
     */
    protected function formatPlaylist(Playlist $playlist, bool $isOwner): array
    {
        $data = [
            'id' => $playlist->id,
            'name' => $playlist->name,
            'description' => $playlist->description,
            'visibility' => $playlist->visibility,
            'share_code' => $playlist->share_code,
            'slug' => $playlist->slug,
            'items_count' => $playlist->items_count,
            'created_at' => $playlist->created_at->toIso8601String(),
            'updated_at' => $playlist->updated_at->toIso8601String(),
        ];

        if (! $isOwner && $playlist->relationLoaded('user')) {
            $data['user'] = [
                'id' => $playlist->user->id,
                'name' => $playlist->user->name,
            ];
        }

        return $data;
    }

    /**
     * Create a new playlist.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['nullable', Rule::in(['private', 'public', 'unlisted'])],
        ]);

        $playlist = $request->user()->playlists()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'visibility' => $validated['visibility'] ?? 'private',
        ]);

        return response()->json([
            'playlist' => [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'description' => $playlist->description,
                'visibility' => $playlist->visibility,
                'share_code' => $playlist->share_code,
                'items_count' => 0,
                'created_at' => $playlist->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Get a playlist with its items.
     */
    public function show(Request $request, string $shareCode): JsonResponse
    {
        $playlist = Playlist::where('share_code', $shareCode)->first();

        if (! $playlist) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $user = $request->user();
        $isOwner = $user && $playlist->user_id === $user->id;

        // Check access - owner can always view, otherwise must be public/unlisted
        if (! $isOwner && ! $playlist->isPubliclyAccessible()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $playlist->load(['items.liveset.edition', 'user:id,name']);

        return response()->json([
            'playlist' => [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'description' => $playlist->description,
                'visibility' => $playlist->visibility,
                'share_code' => $playlist->share_code,
                'slug' => $playlist->slug,
                'is_owner' => $isOwner,
                'user' => ! $isOwner ? [
                    'id' => $playlist->user->id,
                    'name' => $playlist->user->name,
                ] : null,
                'items' => $playlist->items->map(fn ($item) => [
                    'id' => $item->id,
                    'liveset_id' => $item->liveset_id,
                    'position' => $item->position,
                    'liveset' => $item->liveset ? [
                        'id' => $item->liveset->id,
                        'title' => $item->liveset->title,
                        'artist_name' => $item->liveset->artist_name,
                        'duration_in_seconds' => $item->liveset->duration_in_seconds,
                        'edition' => $item->liveset->edition ? [
                            'id' => $item->liveset->edition->id,
                            'number' => $item->liveset->edition->number,
                        ] : null,
                    ] : null,
                ]),
                'created_at' => $playlist->created_at->toIso8601String(),
                'updated_at' => $playlist->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update a playlist.
     */
    public function update(Request $request, string $shareCode): JsonResponse
    {
        $playlist = $this->getOwnedPlaylist($request, $shareCode);

        if ($playlist instanceof JsonResponse) {
            return $playlist;
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['nullable', Rule::in(['private', 'public', 'unlisted'])],
        ]);

        $playlist->update($validated);

        return response()->json([
            'playlist' => [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'description' => $playlist->description,
                'visibility' => $playlist->visibility,
                'share_code' => $playlist->share_code,
                'slug' => $playlist->slug,
                'updated_at' => $playlist->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete a playlist.
     */
    public function destroy(Request $request, string $shareCode): JsonResponse
    {
        $playlist = $this->getOwnedPlaylist($request, $shareCode);

        if ($playlist instanceof JsonResponse) {
            return $playlist;
        }

        $playlist->delete();

        return response()->json([
            'message' => 'Playlist deleted.',
        ]);
    }

    /**
     * Add a liveset to a playlist.
     */
    public function addItem(Request $request, string $shareCode): JsonResponse
    {
        $playlist = $this->getOwnedPlaylist($request, $shareCode);

        if ($playlist instanceof JsonResponse) {
            return $playlist;
        }

        $validated = $request->validate([
            'liveset_id' => ['required', 'integer', 'exists:livesets,id'],
        ]);

        // Check if already in playlist
        if ($playlist->items()->where('liveset_id', $validated['liveset_id'])->exists()) {
            return response()->json(['message' => 'Already in playlist.'], 409);
        }

        // Get the next position
        $maxPosition = $playlist->items()->max('position') ?? -1;

        $item = $playlist->items()->create([
            'liveset_id' => $validated['liveset_id'],
            'position' => $maxPosition + 1,
        ]);

        $playlist->touch();

        return response()->json([
            'item' => [
                'id' => $item->id,
                'liveset_id' => $item->liveset_id,
                'position' => $item->position,
            ],
        ], 201);
    }

    /**
     * Reorder playlist items.
     */
    public function reorderItems(Request $request, string $shareCode): JsonResponse
    {
        $playlist = $this->getOwnedPlaylist($request, $shareCode);

        if ($playlist instanceof JsonResponse) {
            return $playlist;
        }

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:playlist_items,id'],
            'items.*.position' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['items'] as $itemData) {
            $playlist->items()
                ->where('id', $itemData['id'])
                ->update(['position' => $itemData['position']]);
        }

        $playlist->touch();

        return response()->json([
            'message' => 'Items reordered.',
        ]);
    }

    /**
     * Remove a liveset from a playlist.
     */
    public function removeItem(Request $request, string $shareCode, Liveset $liveset): JsonResponse
    {
        $playlist = $this->getOwnedPlaylist($request, $shareCode);

        if ($playlist instanceof JsonResponse) {
            return $playlist;
        }

        $deleted = $playlist->items()
            ->where('liveset_id', $liveset->id)
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Not in playlist.'], 404);
        }

        $playlist->touch();

        return response()->json([
            'message' => 'Removed from playlist.',
        ]);
    }

    /**
     * Regenerate the playlist's share code.
     */
    public function regenerateCode(Request $request, string $shareCode): JsonResponse
    {
        $playlist = $this->getOwnedPlaylist($request, $shareCode);

        if ($playlist instanceof JsonResponse) {
            return $playlist;
        }

        $newCode = $playlist->regenerateShareCode();

        return response()->json([
            'share_code' => $newCode,
            'slug' => $playlist->slug,
        ]);
    }

    /**
     * Get a playlist owned by the current user.
     */
    protected function getOwnedPlaylist(Request $request, string $shareCode): Playlist|JsonResponse
    {
        $playlist = Playlist::where('share_code', $shareCode)->first();

        if (! $playlist) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($playlist->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $playlist;
    }
}
