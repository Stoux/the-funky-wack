<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InviteCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InviteController extends Controller
{
    /**
     * List the user's invite codes.
     */
    public function index(Request $request): JsonResponse
    {
        $invites = $request->user()
            ->inviteCodes()
            ->with('usedByUser:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InviteCode $invite) => [
                'id' => $invite->id,
                'code' => $invite->code,
                'used_by' => $invite->usedByUser ? [
                    'id' => $invite->usedByUser->id,
                    'name' => $invite->usedByUser->name,
                ] : null,
                'used_at' => $invite->used_at?->toIso8601String(),
                'created_at' => $invite->created_at->toIso8601String(),
            ]);

        return response()->json([
            'invites' => $invites,
        ]);
    }

    /**
     * Generate a new invite code.
     */
    public function store(Request $request): JsonResponse
    {
        $invite = $request->user()->inviteCodes()->create([
            'code' => InviteCode::generateCode(),
        ]);

        return response()->json([
            'invite' => [
                'id' => $invite->id,
                'code' => $invite->code,
                'created_at' => $invite->created_at->toIso8601String(),
            ],
        ], 201);
    }
}
