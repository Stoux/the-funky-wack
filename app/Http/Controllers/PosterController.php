<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PosterController extends Controller
{
    public function show(string $version, string $path, Request $request): Response
    {
        // Basic sanity for version; not used for lookup
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $version)) {
            abort(404);
        }

        // Normalize path to avoid leading slashes and control chars
        $path = ltrim($path, "/\t\n\r\0\x0B");

        // Fail fast on empty/absolute/traversal
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            abort(404);
        }

        // Restrict to expected public disk prefixes
        if (!str_starts_with($path, 'posters/') && !str_starts_with($path, 'editions/') && !str_starts_with($path, 'images/')) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($path)) {
            abort(404);
        }

        $headers = [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ];

        return $disk->response($path, null, $headers);
    }
}
