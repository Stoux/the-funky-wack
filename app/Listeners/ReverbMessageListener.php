<?php

namespace App\Listeners;

use App\Jobs\ProcessPlaybackEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Reverb\Events\MessageReceived;

class ReverbMessageListener
{
    /**
     * Maximum events per session per minute (burst limit).
     */
    protected const RATE_LIMIT_PER_MINUTE = 200;

    /**
     * Maximum events per session per 15 minutes (sustained limit).
     * Normal usage: ~5-10/min = 75-150 per 15min.
     */
    protected const RATE_LIMIT_PER_15_MIN = 800;

    /**
     * Handle the event.
     *
     * NOTE: This runs inside the Reverb process - must be FAST.
     * Only decode the message and dispatch to queue, no DB work.
     */
    public function handle(MessageReceived $event): void
    {
        $message = json_decode($event->message, true);

        if (! $message || ! isset($message['event'])) {
            return;
        }

        // Only process client events (whispers) on playback channels
        if (! Str::startsWith($message['event'], 'client-')) {
            return;
        }

        $channel = $message['channel'] ?? null;
        if (! $channel || ! Str::startsWith($channel, 'presence-playback.')) {
            return;
        }

        // Skip rebroadcast messages (they include user_id from the presence channel auth)
        if (isset($message['user_id'])) {
            return;
        }

        // Extract session ID from channel name
        $sessionId = Str::after($channel, 'presence-playback.');
        $eventName = Str::after($message['event'], 'client-');
        $data = $message['data'] ?? [];

        // Rate limiting per session using proper Laravel RateLimiter
        // Two tiers: burst (per minute) and sustained (per 15 min)
        $burstKey = "playback:burst:{$sessionId}";
        $sustainedKey = "playback:sustained:{$sessionId}";

        if (RateLimiter::tooManyAttempts($burstKey, self::RATE_LIMIT_PER_MINUTE)) {
            Log::warning('Playback burst rate limit exceeded', [
                'session_id' => $sessionId,
                'event' => $eventName,
                'retry_after' => RateLimiter::availableIn($burstKey),
            ]);

            return;
        }

        if (RateLimiter::tooManyAttempts($sustainedKey, self::RATE_LIMIT_PER_15_MIN)) {
            Log::warning('Playback sustained rate limit exceeded', [
                'session_id' => $sessionId,
                'event' => $eventName,
                'retry_after' => RateLimiter::availableIn($sustainedKey),
            ]);

            return;
        }

        RateLimiter::hit($burstKey, 60);
        RateLimiter::hit($sustainedKey, 900);

        // Create a unique key for this message to prevent duplicate processing
        // This handles the case where the same message is received multiple times
        $messageHash = md5($event->message);
        $dedupeKey = "playback_msg:{$messageHash}";

        if (Cache::has($dedupeKey)) {
            return;
        }

        // Short TTL - just needs to catch near-simultaneous duplicates
        Cache::put($dedupeKey, true, 2);

        // Queue the processing (don't block the event loop)
        ProcessPlaybackEvent::dispatch($sessionId, $eventName, $data);
    }
}
