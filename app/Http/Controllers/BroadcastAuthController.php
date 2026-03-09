<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;

class BroadcastAuthController extends Controller
{
    /**
     * Authenticate the request for channel access.
     * Unlike Laravel's default, this allows guest users for specific channels.
     */
    public function authenticate(Request $request): JsonResponse
    {
        \Log::debug('BroadcastAuthController::authenticate called', [
            'channel_name' => $request->input('channel_name'),
            'socket_id' => $request->input('socket_id'),
        ]);

        $channelName = $request->input('channel_name');

        // For playback channels, allow guests
        if (str_starts_with($channelName, 'presence-playback.')) {
            return $this->authorizePlaybackChannel($request, $channelName);
        }

        // For listen-along and live channels, allow guests
        if (str_starts_with($channelName, 'presence-listen-along.') || $channelName === 'presence-live') {
            return $this->authorizeGuestPresenceChannel($request, $channelName);
        }

        // For other channels, require authentication
        if (! $request->user()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return Broadcast::auth($request);
    }

    /**
     * Authorize access to playback presence channel.
     * Allows both authenticated and anonymous users.
     */
    protected function authorizePlaybackChannel(Request $request, string $channelName): \Illuminate\Http\JsonResponse
    {
        // Extract session ID from channel name (presence-playback.{sessionId})
        $requestedSessionId = str_replace('presence-playback.', '', $channelName);
        $currentSessionId = $request->session()->getId();

        \Log::debug('Playback channel auth', [
            'channel' => $channelName,
            'requested_session_id' => $requestedSessionId,
            'current_session_id' => $currentSessionId,
            'match' => $currentSessionId === $requestedSessionId,
        ]);

        // Verify session ID matches
        if ($currentSessionId !== $requestedSessionId) {
            return response()->json(['message' => 'Session mismatch'], 403);
        }

        $user = $request->user();
        $socketId = $request->input('socket_id');

        // Cache session → user mapping for whisper processing (24 hours)
        if ($user) {
            Cache::put("playback_session_user:{$currentSessionId}", $user->id, now()->addHours(24));
        }

        // Build presence channel auth response
        $channelData = [
            'user_id' => $user?->id ?? 'guest_'.substr($currentSessionId, 0, 8),
            'user_info' => [
                'session_id' => $currentSessionId,
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Anonymous',
            ],
        ];

        // Generate auth signature
        $signature = $this->generateSignature($socketId, $channelName, $channelData);

        return response()->json([
            'auth' => $this->getReverbKey().':'.$signature,
            'channel_data' => json_encode($channelData),
        ]);
    }

    /**
     * Authorize access to guest-friendly presence channels (live, listen-along).
     */
    protected function authorizeGuestPresenceChannel(Request $request, string $channelName): JsonResponse
    {
        $user = $request->user();
        $socketId = $request->input('socket_id');
        $sessionId = $request->session()->getId();

        $channelData = [
            'user_id' => $user?->id ?? 'guest_'.substr($sessionId, 0, 8),
            'user_info' => [
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Anonymous',
            ],
        ];

        $signature = $this->generateSignature($socketId, $channelName, $channelData);

        return response()->json([
            'auth' => $this->getReverbKey().':'.$signature,
            'channel_data' => json_encode($channelData),
        ]);
    }

    /**
     * Generate HMAC signature for Pusher/Reverb auth.
     */
    protected function generateSignature(string $socketId, string $channelName, array $channelData): string
    {
        $stringToSign = $socketId.':'.$channelName.':'.json_encode($channelData);

        return hash_hmac('sha256', $stringToSign, $this->getReverbSecret());
    }

    /**
     * Get the Reverb app key.
     */
    protected function getReverbKey(): string
    {
        $apps = config('reverb.apps.apps', []);

        return $apps[0]['key'] ?? throw new \RuntimeException('Reverb app key not configured');
    }

    /**
     * Get the Reverb app secret.
     */
    protected function getReverbSecret(): string
    {
        $apps = config('reverb.apps.apps', []);

        return $apps[0]['secret'] ?? throw new \RuntimeException('Reverb app secret not configured');
    }
}
