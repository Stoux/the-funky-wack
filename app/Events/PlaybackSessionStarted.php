<?php

namespace App\Events;

use App\Models\PlayHistory;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlaybackSessionStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PlayHistory $playHistory,
        public string $sessionId
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('playback.'.$this->sessionId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'session.started';
    }

    public function broadcastWith(): array
    {
        return [
            'play_history_id' => $this->playHistory->id,
            'liveset_id' => $this->playHistory->liveset_id,
            'position' => $this->playHistory->started_at_position,
            'quality' => $this->playHistory->quality,
        ];
    }
}
