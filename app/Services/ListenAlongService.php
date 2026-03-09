<?php

namespace App\Services;

use App\Events\ListenAlongHostAction;
use App\Events\ListenAlongListenerUpdate;
use App\Events\LiveSessionsUpdated;
use App\Models\ListenRoom;
use App\Models\ListenRoomMember;
use App\Models\PlayHistory;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ListenAlongService
{
    public const STALE_ROOM_MINUTES = 2;

    private const ROOM_CACHE_TTL_SECONDS = 60;

    /**
     * Create a listen room when a host starts playing.
     */
    public function createRoom(PlayHistory $playHistory): ListenRoom
    {
        $room = ListenRoom::create([
            'channel_token' => Str::random(32),
            'started_at' => now(),
        ]);

        $room->members()->create([
            'user_id' => $playHistory->user_id,
            'session_id' => $playHistory->session_id,
            'client_id' => $playHistory->client_id,
            'role' => 'host',
            'mode' => 'synced',
            'play_history_id' => $playHistory->id,
            'joined_at' => now(),
        ]);

        // Cache session → room mapping
        $this->cacheSessionRoom($playHistory->session_id, $room->id);

        Log::debug('ListenAlong: room created', [
            'room_id' => $room->id,
            'channel_token' => $room->channel_token,
            'play_history_id' => $playHistory->id,
        ]);

        event(new LiveSessionsUpdated);

        return $room;
    }

    /**
     * End a listen room when the host stops playing.
     */
    public function endRoom(ListenRoom $room): void
    {
        $room->update(['ended_at' => now()]);

        // Notify synced listeners that the host stopped
        event(new ListenAlongHostAction($room->channel_token, 'host.stop', []));

        // Mark all remaining active members (host + listeners) as left
        $room->activeMembers()->update(['left_at' => now()]);

        // Invalidate session → room cache for the host
        $host = $room->host;
        if ($host) {
            $this->clearSessionRoomCache($host->session_id);
        }

        // Clean up if no listeners ever joined
        if (! $room->had_listeners) {
            $room->delete();
        }

        Log::debug('ListenAlong: room ended', [
            'room_id' => $room->id,
            'channel_token' => $room->channel_token,
        ]);

        event(new LiveSessionsUpdated);
    }

    /**
     * Update the host's play history when they switch tracks.
     */
    public function updateHostPlayHistory(ListenRoom $room, PlayHistory $playHistory): void
    {
        $host = $room->host;
        if (! $host) {
            return;
        }

        $host->update(['play_history_id' => $playHistory->id]);

        // Broadcast track change to synced listeners
        if ($room->activeSyncedListeners()->exists()) {
            event(new ListenAlongHostAction($room->channel_token, 'host.track-change', [
                'liveset_id' => $playHistory->liveset_id,
                'position' => $playHistory->started_at_position,
                'quality' => $playHistory->quality,
            ]));
        }
    }

    /**
     * Join a listener to a room.
     */
    public function joinRoom(
        string $channelToken,
        string $clientId,
        ?int $userId = null,
        string $mode = 'synced',
        ?int $playHistoryId = null
    ): ?ListenRoomMember {
        $room = ListenRoom::where('channel_token', $channelToken)
            ->whereNull('ended_at')
            ->first();

        if (! $room) {
            return null;
        }

        // If this client is currently hosting a room, end it first
        $hostedRoom = $this->findActiveRoomHostedByClient($clientId);
        if ($hostedRoom && $hostedRoom->id !== $room->id) {
            Log::debug('ListenAlong: ending hosted room before joining another', [
                'hosted_room_id' => $hostedRoom->id,
                'joining_room_id' => $room->id,
                'client_id' => $clientId,
            ]);
            $this->endRoom($hostedRoom);
        }

        $now = now();
        $isIndependent = $mode === 'independent';

        $member = $room->members()->create([
            'user_id' => $userId,
            'session_id' => $clientId,
            'client_id' => $clientId,
            'role' => 'listener',
            'mode' => $mode,
            'play_history_id' => $playHistoryId,
            'joined_at' => $now,
            'left_at' => $isIndependent ? $now : null,
        ]);

        if (! $isIndependent) {
            $count = $room->activeSyncedListeners()->count();

            event(new ListenAlongListenerUpdate($channelToken, 'listener.joined', [
                'name' => $this->resolveDisplayName($userId, null, true),
                'count' => $count,
            ]));

            event(new LiveSessionsUpdated);
        }

        Log::debug('ListenAlong: member joined', [
            'room_id' => $room->id,
            'member_id' => $member->id,
            'mode' => $mode,
        ]);

        return $member;
    }

    /**
     * Remove a listener from a room.
     */
    public function leaveRoom(string $channelToken, string $clientId): void
    {
        $room = ListenRoom::where('channel_token', $channelToken)->first();
        if (! $room) {
            return;
        }

        $member = $room->activeMembers()
            ->where('client_id', $clientId)
            ->where('role', 'listener')
            ->first();

        if (! $member) {
            return;
        }

        $member->update(['left_at' => now()]);

        $count = $room->activeSyncedListeners()->count();

        event(new ListenAlongListenerUpdate($channelToken, 'listener.left', [
            'count' => $count,
        ]));

        event(new LiveSessionsUpdated);

        Log::debug('ListenAlong: member left', [
            'room_id' => $room->id,
            'member_id' => $member->id,
        ]);
    }

    /**
     * Detach a synced listener to independent mode.
     */
    public function detachListener(string $channelToken, string $clientId): void
    {
        $room = ListenRoom::where('channel_token', $channelToken)->first();
        if (! $room) {
            return;
        }

        $member = $room->activeMembers()
            ->where('client_id', $clientId)
            ->where('role', 'listener')
            ->first();

        if (! $member) {
            return;
        }

        $member->update([
            'mode' => 'independent',
            'left_at' => now(),
        ]);

        $count = $room->activeSyncedListeners()->count();

        event(new ListenAlongListenerUpdate($channelToken, 'listener.left', [
            'count' => $count,
        ]));

        event(new LiveSessionsUpdated);
    }

    /**
     * Get all active sessions with member info for the Live page.
     *
     * @return array<int, array{channel_token: string, host: array, listeners_count: int}>
     */
    public function getActiveSessions(?int $viewerUserId, bool $viewerAuthenticated = false): array
    {
        $rooms = ListenRoom::query()
            ->whereNull('ended_at')
            ->withCount(['activeSyncedListeners as listeners_count'])
            ->with([
                'host.user:id,name,listening_visibility',
                'host.playHistory.liveset:id,title,artist_name,edition_id,duration_in_seconds',
                'host.playHistory.liveset.edition:id,number',
            ])
            ->get();

        return $rooms->map(function (ListenRoom $room) use ($viewerUserId, $viewerAuthenticated) {
            $host = $room->host;
            if (! $host) {
                return null;
            }

            $playHistory = $host->playHistory;
            if (! $playHistory) {
                return null;
            }

            return [
                'channel_token' => $room->channel_token,
                'started_at' => $room->started_at->toISOString(),
                'host' => [
                    'name' => $this->resolveDisplayNameFromUser(
                        $host->user,
                        $viewerUserId,
                        $viewerAuthenticated
                    ),
                    'user_id' => $host->user_id,
                ],
                'liveset' => $playHistory->liveset ? [
                    'id' => $playHistory->liveset->id,
                    'title' => $playHistory->liveset->title,
                    'artist_name' => $playHistory->liveset->artist_name,
                    'edition_number' => $playHistory->liveset->edition?->number,
                    'duration_in_seconds' => $playHistory->liveset->duration_in_seconds,
                ] : null,
                'position' => $playHistory->ended_at_position ?? $playHistory->started_at_position ?? 0,
                'position_updated_at' => $playHistory->updated_at->toISOString(),
                'quality' => $playHistory->quality,
                'listeners_count' => $room->listeners_count ?? 0,
            ];
        })->filter()->values()->toArray();
    }

    /**
     * Resolve display name from an already-loaded User model.
     */
    public function resolveDisplayNameFromUser(?User $user, ?int $viewerUserId, bool $viewerAuthenticated): string
    {
        if (! $user) {
            return 'Anonymous';
        }

        // Always show own name
        if ($viewerUserId && $user->id === $viewerUserId) {
            return $user->name;
        }

        return match ($user->listening_visibility ?? 'everyone') {
            'everyone' => $user->name,
            'authenticated' => $viewerAuthenticated ? $user->name : 'Anonymous',
            'nobody' => 'Anonymous',
            default => 'Anonymous',
        };
    }

    /**
     * Resolve display name by user ID (for contexts where user isn't pre-loaded).
     */
    public function resolveDisplayName(?int $userId, ?int $viewerUserId, bool $viewerAuthenticated): string
    {
        if (! $userId) {
            return 'Anonymous';
        }

        $user = User::select('id', 'name', 'listening_visibility')->find($userId);

        return $this->resolveDisplayNameFromUser($user, $viewerUserId, $viewerAuthenticated);
    }

    /**
     * Find the active room hosted by a given client ID.
     */
    public function findActiveRoomHostedByClient(string $clientId): ?ListenRoom
    {
        $member = ListenRoomMember::query()
            ->where('client_id', $clientId)
            ->where('role', 'host')
            ->whereNull('left_at')
            ->with('room')
            ->first();

        if (! $member || ! $member->room || $member->room->ended_at !== null) {
            return null;
        }

        return $member->room;
    }

    /**
     * Find the active room for a given session ID (as host), with caching.
     */
    public function findActiveRoomForSession(string $sessionId): ?ListenRoom
    {
        $roomId = Cache::get("listen_room_session:{$sessionId}");

        if ($roomId) {
            $room = ListenRoom::find($roomId);
            if ($room && $room->ended_at === null) {
                return $room;
            }

            // Stale cache — clear it
            $this->clearSessionRoomCache($sessionId);
        }

        // Fallback to DB query
        $member = ListenRoomMember::query()
            ->where('session_id', $sessionId)
            ->where('role', 'host')
            ->whereNull('left_at')
            ->with('room')
            ->first();

        if (! $member || ! $member->room || $member->room->ended_at !== null) {
            return null;
        }

        // Populate cache for next time
        $this->cacheSessionRoom($sessionId, $member->room->id);

        return $member->room;
    }

    /**
     * Delete ended rooms that never had listeners.
     */
    public function cleanupEmptyEndedRooms(): int
    {
        $roomIds = ListenRoom::query()
            ->whereNotNull('ended_at')
            ->whereDoesntHave('members', function ($query) {
                $query->where('role', 'listener');
            })
            ->pluck('id');

        if ($roomIds->isEmpty()) {
            return 0;
        }

        // Delete members first (cascade would handle this, but be explicit)
        ListenRoomMember::whereIn('listen_room_id', $roomIds)->delete();
        $count = ListenRoom::whereIn('id', $roomIds)->delete();

        Log::debug('ListenAlong: cleaned up empty rooms', ['count' => $count]);

        return $count;
    }

    /**
     * End rooms where the host hasn't updated in a while (network disconnect).
     */
    public function endStaleRooms(): int
    {
        $staleThreshold = now()->subMinutes(self::STALE_ROOM_MINUTES);
        $count = 0;

        $rooms = ListenRoom::query()
            ->whereNull('ended_at')
            ->with('host.playHistory')
            ->get();

        foreach ($rooms as $room) {
            $host = $room->host;
            if (! $host) {
                $this->endRoom($room);
                $count++;

                continue;
            }

            $playHistory = $host->playHistory;
            if (! $playHistory || $playHistory->updated_at->lt($staleThreshold)) {
                $this->endRoom($room);
                $count++;
            }
        }

        if ($count > 0) {
            Log::debug('ListenAlong: ended stale rooms', ['count' => $count]);
        }

        return $count;
    }

    /**
     * Broadcast a host action to synced listeners.
     */
    public function broadcastHostAction(ListenRoom $room, string $event, array $data): void
    {
        if ($room->activeSyncedListeners()->exists()) {
            event(new ListenAlongHostAction($room->channel_token, $event, $data));
        }
    }

    private function cacheSessionRoom(string $sessionId, int $roomId): void
    {
        Cache::put("listen_room_session:{$sessionId}", $roomId, self::ROOM_CACHE_TTL_SECONDS);
    }

    private function clearSessionRoomCache(string $sessionId): void
    {
        Cache::forget("listen_room_session:{$sessionId}");
    }
}
