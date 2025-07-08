<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Super ghetto password protection
 */
class AdminLoggedIn
{
    public const SESSION_KEY = 'tfw_admin_logged_in';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isLoggedIn()) {
            return redirect()->route('login');
        }

        return $next($request);
    }

    /**
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return session()->has(self::SESSION_KEY);
    }
}
