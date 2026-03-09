<?php

namespace App\Jobs;

use App\Models\ListenRoom;
use App\Services\ListenAlongService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EndListenRoomIfStale implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $sessionId,
        public int $roomId
    ) {
        $this->onQueue('playback');
    }

    public function handle(ListenAlongService $listenAlongService): void
    {
        // Check if the pending end flag is still set — if cleared, a new start cancelled it
        $pendingRoomId = Cache::get("listen_room_pending_end:{$this->sessionId}");

        if ($pendingRoomId !== $this->roomId) {
            Log::debug('ListenAlong: pending room end cancelled (track switch)', [
                'session_id' => $this->sessionId,
                'room_id' => $this->roomId,
            ]);

            return;
        }

        $room = ListenRoom::find($this->roomId);

        if (! $room || $room->ended_at !== null) {
            return;
        }

        Cache::forget("listen_room_pending_end:{$this->sessionId}");

        $listenAlongService->endRoom($room);

        Log::debug('ListenAlong: room ended after grace period', [
            'session_id' => $this->sessionId,
            'room_id' => $this->roomId,
        ]);
    }
}
