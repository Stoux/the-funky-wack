<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceCodeController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\InviteController;
use App\Http\Controllers\Api\ListenAlongController;
use App\Http\Controllers\Api\PlaybackController;
use App\Http\Controllers\Api\PlaylistController;
use App\Http\Controllers\Api\QueueHealthController;
use App\Http\Controllers\Api\ReverbWebhookController;
use App\Http\Controllers\Api\UserSettingsController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\BroadcastAuthController;
use Illuminate\Support\Facades\Route;

// Custom broadcast auth that allows guests for playback channels
Route::post('/broadcast/auth', [BroadcastAuthController::class, 'authenticate']);

// Public API endpoints
Route::get('/editions', [ApiController::class, 'editions'])->name('api.editions');

// Authentication routes
Route::prefix('auth')->group(function () {
    // Rate limit: 5 attempts per minute for login/register
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
    });

    // Device code authentication
    Route::middleware('throttle:5,1')->post('/device-code', [DeviceCodeController::class, 'store']);
    Route::middleware('throttle:30,1')->get('/device-code/{code}/poll', [DeviceCodeController::class, 'poll']);
    Route::middleware('auth:sanctum')->post('/device-code/{code}/authorize', [DeviceCodeController::class, 'authorize']);
});

// Play tracking - works for both authenticated and anonymous users
// Rate limit: 30 requests per minute (allows progress updates every 2 seconds)
Route::prefix('playback')->middleware('throttle:30,1')->group(function () {
    Route::post('/track', [PlaybackController::class, 'recordPlay']);
});

// Public playlist endpoints (no auth required, authenticated users get extra data)
Route::get('/playlists', [PlaylistController::class, 'index']);
Route::get('/playlists/{shareCode}', [PlaylistController::class, 'show']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Invite codes
    Route::get('/invites', [InviteController::class, 'index']);
    Route::middleware('throttle:10,1')->post('/invites', [InviteController::class, 'store']);
    Route::delete('/invites/{invite}', [InviteController::class, 'destroy']);

    // Devices
    Route::get('/devices', [DeviceController::class, 'index']);
    Route::post('/devices/register', [DeviceController::class, 'register']);
    Route::put('/devices/{device}', [DeviceController::class, 'update']);
    Route::put('/devices/{device}/hide', [DeviceController::class, 'hide']);
    Route::put('/devices/{device}/show', [DeviceController::class, 'show']);
    Route::delete('/devices/{device}', [DeviceController::class, 'destroy']);

    // Playback history and positions (authenticated only)
    Route::prefix('playback')->group(function () {
        Route::get('/history', [PlaybackController::class, 'history']);
        Route::get('/positions', [PlaybackController::class, 'positions']);
        Route::put('/positions/{liveset}', [PlaybackController::class, 'savePosition']);
        Route::delete('/positions/{liveset}', [PlaybackController::class, 'clearPosition']);
    });

    // Favorites
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/{liveset}', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{liveset}', [FavoriteController::class, 'destroy']);

    // Playlists
    Route::post('/playlists', [PlaylistController::class, 'store']);
    Route::put('/playlists/{shareCode}', [PlaylistController::class, 'update']);
    Route::delete('/playlists/{shareCode}', [PlaylistController::class, 'destroy']);
    Route::post('/playlists/{shareCode}/items', [PlaylistController::class, 'addItem']);
    Route::put('/playlists/{shareCode}/items', [PlaylistController::class, 'reorderItems']);
    Route::delete('/playlists/{shareCode}/items/{liveset}', [PlaylistController::class, 'removeItem']);
    Route::post('/playlists/{shareCode}/regenerate-code', [PlaylistController::class, 'regenerateCode']);
});

// Listen Along - public endpoints
Route::prefix('live')->group(function () {
    Route::middleware('throttle:30,1')->group(function () {
        Route::get('/sessions', [ListenAlongController::class, 'sessions']);
        Route::get('/rooms/{channelToken}/state', [ListenAlongController::class, 'state']);
    });

    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/rooms/{channelToken}/join', [ListenAlongController::class, 'join']);
        Route::post('/rooms/{channelToken}/leave', [ListenAlongController::class, 'leave']);
        Route::post('/rooms/{channelToken}/detach', [ListenAlongController::class, 'detach']);
        Route::post('/rooms/{channelToken}/pause-sync', [ListenAlongController::class, 'pauseSync']);
        Route::post('/rooms/{channelToken}/resume-sync', [ListenAlongController::class, 'resumeSync']);
    });
});

// Protected routes (require authentication) - user settings
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/settings/visibility', [UserSettingsController::class, 'updateVisibility']);
    Route::put('/settings/profile', [UserSettingsController::class, 'updateProfile']);
    Route::put('/settings/password', [UserSettingsController::class, 'updatePassword']);
});

// Reverb webhook for presence channel events (disconnect detection)
Route::post('/reverb/webhook', [ReverbWebhookController::class, 'handle'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// Queue health monitoring
Route::get('/queue/health', QueueHealthController::class);
