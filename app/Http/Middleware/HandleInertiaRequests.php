<?php

namespace App\Http\Middleware;

use App\Enums\LivesetQuality;
use App\Services\EditionsDataService;
use App\Services\TimetablerService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        // Only load editions data for front-end pages (not admin)
        $isAdminRoute = $request->routeIs('admin.*');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'sessionId' => $request->session()->getId(),
            // Shared editions data for playback on all front pages (only on initial page load, not Inertia navigations)
            'editions' => $isAdminRoute || $request->header('X-Inertia') ? null : fn () => app(EditionsDataService::class)->buildEditionsData(app(TimetablerService::class)),
            'qualities' => $isAdminRoute || $request->header('X-Inertia') ? null : fn () => LivesetQuality::options(),
        ];
    }
}
