<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// User-specific private channel for notifications
Broadcast::channel('user.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

// Presence channel for playback tracking (works for both authenticated and anonymous users)
// Returns user info for presence tracking - server detects join/leave for connection state
Broadcast::channel('playback.{sessionId}', function ($user, string $sessionId) {
    return [
        'session_id' => $sessionId,
        'user_id' => $user?->id ?? null,
        'user_name' => $user?->name ?? 'Anonymous',
    ];
});

// Presence channel for live listener counts on a liveset
Broadcast::channel('liveset.{livesetId}', function (?User $user, int $livesetId) {
    // Allow both authenticated and anonymous users
    return [
        'id' => $user?->id,
        'name' => $user?->name ?? 'Anonymous',
        'session_id' => session()->getId(),
    ];
});

// Listen-along & live channels (presence-listen-along.{token}, presence-live)
// Authorized in BroadcastAuthController::authorizeGuestPresenceChannel() — NOT here.
// Laravel's default Broadcast::channel() requires an authenticated user, but these
// channels must support anonymous guests. The custom controller handles HMAC signing
// and user_info directly. See: app/Http/Controllers/BroadcastAuthController.php
