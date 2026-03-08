<?php

namespace App\Http\Controllers;

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
     * Show the user's playlists.
     */
    public function playlists(Request $request): Response
    {
        return Inertia::render('User/Playlists');
    }

    /**
     * Show a specific playlist.
     */
    public function playlist(Request $request, int $playlist): Response
    {
        return Inertia::render('User/Playlist', [
            'playlistId' => $playlist,
        ]);
    }

    /**
     * Show a shared playlist by code.
     */
    public function sharedPlaylist(string $code): Response
    {
        return Inertia::render('SharedPlaylist', [
            'shareCode' => $code,
        ]);
    }
}
