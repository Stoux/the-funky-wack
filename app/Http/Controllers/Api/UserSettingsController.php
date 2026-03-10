<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
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

    /**
     * Update the user's name and email.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $request->user()->update($request->validated());

        return response()->json([
            'message' => 'Profile updated.',
            'user' => $request->user()->only('id', 'name', 'email'),
        ]);
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        return response()->json(['message' => 'Password updated.']);
    }
}
