<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CueSheetParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UtilController extends Controller
{
    public function parseCue(CueSheetParser $cueParser, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'string',
        ]);

        $cue = $cueParser->parse($validated['content']);

        return response()->json(array_filter([
            'recorded_at' => $cue['recorded_at']?->toIso8601String(),
            'performer' => $cue['performer'],
        ]) + [
            'songs' => $cueParser->toTracklistLines($cue['tracks']),
        ]);
    }
}
