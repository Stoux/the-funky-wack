<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSettingsController extends Controller
{
    /**
     * Update the user's listening visibility setting.
     */
    public function updateVisibility(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'listening_visibility' => ['required', 'string', 'in:everyone,authenticated,nobody'],
        ]);

        $request->user()->update([
            'listening_visibility' => $validated['listening_visibility'],
        ]);

        return response()->json(['message' => 'Visibility updated.']);
    }
}
