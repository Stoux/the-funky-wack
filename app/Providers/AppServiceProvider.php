<?php

namespace App\Providers;

use App\Http\Middleware\AdminLoggedIn;
use App\Listeners\ChannelRemovedListener;
use App\Listeners\ReverbMessageListener;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Events\PasskeyVerified;
use Laravel\Reverb\Events\ChannelRemoved;
use Laravel\Reverb\Events\MessageReceived;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.force_https')) {
            \URL::forceScheme('https');
        }

        // Register Reverb event listeners for playback tracking
        Event::listen(MessageReceived::class, ReverbMessageListener::class);
        Event::listen(ChannelRemoved::class, ChannelRemovedListener::class);

        // Fetching options happens on every login page view (autofill), verifying is the part to slow down
        RateLimiter::for('passkeys', fn (Request $request) => $request->isMethod('GET')
            ? Limit::perMinute(30)->by($request->user()?->id ?: $request->ip())
            : Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        // A passkey is at least as strong as a password, so it also counts as the fresh login the admin area wants
        Event::listen(PasskeyVerified::class, function (PasskeyVerified $event) {
            if ($event->user->is_admin) {
                session()->put(AdminLoggedIn::SESSION_KEY, now());
            }
        });
    }
}
