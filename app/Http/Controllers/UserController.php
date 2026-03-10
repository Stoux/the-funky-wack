<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Show the user's profile page.
     */
    public function profile(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('User/Profile', [
            'invites' => $user->inviteCodes()
                ->with('usedByUser:id,name')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($invite) => [
                    'id' => $invite->id,
                    'code' => $invite->code,
                    'used_by' => $invite->usedByUser ? [
                        'id' => $invite->usedByUser->id,
                        'name' => $invite->usedByUser->name,
                    ] : null,
                    'used_at' => $invite->used_at?->toIso8601String(),
                    'created_at' => $invite->created_at->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Show the user's play history.
     */
    public function history(Request $request): Response
    {
        return Inertia::render('User/History');
    }

    /**
     * Show the user's favorites.
     */
    public function favorites(Request $request): Response
    {
        return Inertia::render('User/Favorites');
    }

    /**
     * Show the user's devices.
     */
    public function devices(Request $request): Response
    {
        return Inertia::render('User/Devices');
    }

    /**
     * Show the link device page.
     */
    public function linkDevice(Request $request): Response
    {
        return Inertia::render('User/LinkDevice');
    }

    /**
     * Show playlists overview (user's playlists + public playlists).
     */
    public function playlists(Request $request): Response
    {
        return Inertia::render('User/Playlists');
    }

    /**
     * Show a specific playlist by share code.
     */
    public function playlist(Request $request, string $shareCode, ?string $slug = null): Response|RedirectResponse
    {
        $playlist = Playlist::where('share_code', $shareCode)->first();

        if (! $playlist) {
            abort(404);
        }

        $user = $request->user();
        $isOwner = $user && $playlist->user_id === $user->id;

        // Check access - owner can always view, otherwise must be public/unlisted
        if (! $isOwner && ! $playlist->isPubliclyAccessible()) {
            abort(404);
        }

        // Redirect if slug doesn't match current name
        if ($slug !== $playlist->slug) {
            return redirect()->route('playlist.show', [
                'shareCode' => $playlist->share_code,
                'slug' => $playlist->slug,
            ]);
        }

        return Inertia::render('User/Playlist', [
            'shareCode' => $shareCode,
            'isOwner' => $isOwner,
        ]);
    }
}
