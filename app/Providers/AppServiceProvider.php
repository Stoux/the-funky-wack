<?php

namespace App\Providers;

use App\Listeners\ChannelRemovedListener;
use App\Listeners\ReverbMessageListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
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
    }
}
