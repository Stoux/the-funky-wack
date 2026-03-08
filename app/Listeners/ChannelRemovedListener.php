<?php

namespace App\Listeners;

use App\Services\PlayTrackingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Reverb\Events\ChannelRemoved;

class ChannelRemovedListener
{
    public function __construct(
        protected PlayTrackingService $playTrackingService
    ) {}

    /**
     * Handle the ChannelRemoved event.
     *
     * NOTE: This runs inside the Reverb process - keep it fast.
     */
    public function handle(ChannelRemoved $event): void
    {
        $channelName = $event->channel->name();

        // Only handle playback channels
        if (! Str::startsWith($channelName, 'presence-playback.')) {
            return;
        }

        $sessionId = Str::after($channelName, 'presence-playback.');

        Log::debug('ChannelRemoved: playback channel closed', [
            'channel' => $channelName,
            'session_id' => $sessionId,
        ]);

        // Mark the session as disconnected
        $this->playTrackingService->pauseSessionOnDisconnect($sessionId);
    }
}
