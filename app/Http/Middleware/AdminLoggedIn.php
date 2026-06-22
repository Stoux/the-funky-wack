<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminLoggedIn
{
    /**
     * Session key holding the timestamp of the last fresh admin authentication.
     */
    public const SESSION_KEY = 'admin_authenticated_at';

    /**
     * Admin sessions require fresh authentication within this window.
     */
    protected const ADMIN_AUTH_WINDOW_MINUTES = 1440;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_admin) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            return redirect('/login');
        }

        // Require fresh authentication for admin access (not via remember token)
        // and within the admin auth window.
        if (Auth::guard('web')->viaRemember() || ! $this->hasRecentAuthentication($request)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Re-authentication required'], 401);
            }

            // Drop the remember-based session so the guest-only /login page is
            // reachable; logging back in restocks the fresh-auth timestamp.
            Auth::guard('web')->logout();

            return redirect()->guest('/login')
                ->with('message', 'Please log in again to access the admin area.');
        }

        return $next($request);
    }

    /**
     * Check if the user has authenticated recently.
     */
    protected function hasRecentAuthentication(Request $request): bool
    {
        $lastAuth = $request->session()->get(self::SESSION_KEY);

        if (! $lastAuth) {
            return false;
        }

        return now()->diffInMinutes($lastAuth, true) < self::ADMIN_AUTH_WINDOW_MINUTES;
    }
}
