<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlaybackSessionExpired implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $sessionId,
        public ?int $playHistoryId = null,
        public ?int $livesetId = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('playback.'.$this->sessionId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'session.expired';
    }

    public function broadcastWith(): array
    {
        return [
            'play_history_id' => $this->playHistoryId,
            'liveset_id' => $this->livesetId,
            'message' => 'Session expired after 30 minutes of inactivity',
        ];
    }
}
