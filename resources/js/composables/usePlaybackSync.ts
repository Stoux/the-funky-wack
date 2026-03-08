import { ref, onUnmounted } from 'vue';
import { echo } from '@laravel/echo-vue';
import { usePage } from '@inertiajs/vue3';
import { useAuth } from './useAuth';
import { getClientId } from './useDeviceId';

interface PlaybackState {
    livesetId: number;
    position: number;
    quality?: string;
    playHistoryId?: number;
}

interface PlaybackEvent {
    play_history_id: number;
    liveset_id: number;
    position?: number;
    quality?: string;
    plays_count?: number;
    message?: string;
}

const SYNC_INTERVAL_MS = 15000; // 15 seconds
const SEEK_THRESHOLD_SECONDS = 5; // Position jump > 5s is considered a seek
const PAUSE_DEBOUNCE_MS = 2000; // Only register pause if paused for > 2 seconds (filters buffering)

// Module-level state for tracking actual play time
let actualPlayTimeMs = 0;
let lastPlayTimestamp: number | null = null;
let isCurrentlyPlaying = false;
let hasSetupBeforeUnload = false;

// Track last known position for seek detection
let lastSyncedPosition = 0;

// Debounce timer for pause events (filters buffering)
let pauseDebounceTimer: number | null = null;

// Track if we just started a session (to avoid sending resume immediately after start)
let justStartedSession = false;

// Module-level playback state (shared across components)
const currentPlayback = ref<PlaybackState | null>(null);
const syncTimer = ref<number | null>(null);
const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true);
const isConnected = ref(false);
const sessionExpired = ref(false);

// Session ID for the presence channel
let sessionId: string | null = null;
let presenceChannel: any = null;

// Pre-fetched client ID (needs to be sync for some operations)
let cachedClientId: string | null = null;

// Pending start callback - resolved when server broadcasts session.started
let pendingStartResolve: ((playHistoryId: number) => void) | null = null;

// Pre-fetch client ID on module load
getClientId().then(id => { cachedClientId = id; }).catch(() => {});

export function usePlaybackSync() {
    const { isAuthenticated } = useAuth();
    const echoInstance = echo();

    // Setup beforeunload handler once
    if (!hasSetupBeforeUnload) {
        hasSetupBeforeUnload = true;

        window.addEventListener('beforeunload', () => {
            if (currentPlayback.value?.playHistoryId && presenceChannel) {
                console.log('[PlaybackSync] beforeunload: sending stop via whisper');
                // Send stop whisper - server will receive via webhook
                whisperToServer('stop', {
                    play_history_id: currentPlayback.value.playHistoryId,
                    position: currentPlayback.value.position,
                    duration_listened: getActualPlayTimeSeconds(),
                });
            }
        });

        // Track online/offline status
        window.addEventListener('online', () => {
            isOnline.value = true;
        });
        window.addEventListener('offline', () => {
            isOnline.value = false;
        });
    }

    /**
     * Join the presence channel for WebSocket-based playback tracking.
     * All events are sent/received via WebSocket.
     */
    async function joinPresenceChannel(): Promise<void> {
        // Get session ID
        if (!sessionId) {
            sessionId = getSessionId();
            console.log('[PlaybackSync] Got session ID:', sessionId);
        }

        if (!sessionId) {
            console.warn('[PlaybackSync] Cannot join presence channel: no session ID available');
            return;
        }

        if (presenceChannel) {
            console.log('[PlaybackSync] Already connected to presence channel');
            return;
        }

        console.log('[PlaybackSync] Joining presence channel: playback.' + sessionId);

        presenceChannel = echoInstance.join(`playback.${sessionId}`)
            .here(() => {
                isConnected.value = true;
                sessionExpired.value = false;
                console.log('[PlaybackSync] Connected to presence channel');
            })
            .leaving(() => {
                isConnected.value = false;
                console.log('[PlaybackSync] Left presence channel');
            })
            .error((error: Error) => {
                console.error('[PlaybackSync] Presence channel error:', error);
                isConnected.value = false;
            })
            // Listen for server-broadcast events
            .listen('.session.started', (event: PlaybackEvent) => {
                console.log('[PlaybackSync] Session started event received:', event);

                // If we're waiting for a start response, resolve it
                if (pendingStartResolve) {
                    pendingStartResolve(event.play_history_id);
                    pendingStartResolve = null;
                } else if (currentPlayback.value) {
                    // Update play_history_id if this is from another tab/device
                    currentPlayback.value.playHistoryId = event.play_history_id;
                }
            })
            .listen('.session.expired', (event: PlaybackEvent) => {
                console.log('[PlaybackSync] Session expired event:', event);
                sessionExpired.value = true;

                // If audio is still playing, auto-start a new session
                if (isCurrentlyPlaying && currentPlayback.value) {
                    const { livesetId, position, quality } = currentPlayback.value;
                    currentPlayback.value = null;
                    startPlayback(livesetId, position, quality);
                }
            })
            .listen('.play.counted', (event: PlaybackEvent) => {
                console.log('[PlaybackSync] Play counted event:', event);
            })
            // Listen for whispers from other tabs/devices
            .listenForWhisper('progress', (data: { position: number; client_id?: string }) => {
                console.debug('[PlaybackSync] Received progress whisper:', data);
            })
            .listenForWhisper('seek', (data: { from_position: number; to_position: number }) => {
                console.debug('[PlaybackSync] Received seek whisper:', data);
            })
            .listenForWhisper('pause', (data: { position: number }) => {
                console.debug('[PlaybackSync] Received pause whisper:', data);
            })
            .listenForWhisper('resume', (data: { position: number }) => {
                console.debug('[PlaybackSync] Received resume whisper:', data);
            });
    }

    /**
     * Leave the presence channel.
     */
    function leavePresenceChannel(): void {
        if (sessionId && presenceChannel) {
            echoInstance.leave(`playback.${sessionId}`);
            presenceChannel = null;
            isConnected.value = false;
        }
    }

    /**
     * Send a whisper (client event) that the server will receive via webhook.
     * Event names are prefixed with 'client-' automatically by Pusher/Reverb.
     */
    function whisperToServer(eventName: string, data: object): void {
        if (!presenceChannel || !isConnected.value) {
            console.warn('[PlaybackSync] Cannot send whisper - not connected');
            return;
        }

        const payload = {
            ...data,
            client_id: cachedClientId,
        };

        console.log('[PlaybackSync] Sending whisper:', eventName, payload);
        presenceChannel.whisper(eventName, payload);
    }

    /**
     * Get Laravel session ID from Inertia page props.
     */
    function getSessionId(): string | null {
        const page = usePage();
        return (page.props.sessionId as string) || null;
    }

    /**
     * Called when audio starts playing.
     */
    function onPlay() {
        console.log('[PlaybackSync] onPlay called, isCurrentlyPlaying:', isCurrentlyPlaying, 'justStartedSession:', justStartedSession);

        // Cancel any pending pause event (was likely just buffering)
        if (pauseDebounceTimer) {
            clearTimeout(pauseDebounceTimer);
            pauseDebounceTimer = null;
        }

        if (!isCurrentlyPlaying) {
            isCurrentlyPlaying = true;
            lastPlayTimestamp = Date.now();

            // Send resume event if we have an active session (but not right after starting)
            if (currentPlayback.value?.playHistoryId && !justStartedSession) {
                console.log('[PlaybackSync] Sending resume whisper for session:', currentPlayback.value.playHistoryId);
                whisperToServer('resume', {
                    play_history_id: currentPlayback.value.playHistoryId,
                    position: currentPlayback.value.position,
                });
            }

            // Clear the flag after first play event
            justStartedSession = false;
        }
    }

    /**
     * Called when audio pauses or buffers.
     * Debounced to filter out brief buffering pauses.
     */
    function onPause() {
        if (isCurrentlyPlaying && lastPlayTimestamp !== null) {
            actualPlayTimeMs += Date.now() - lastPlayTimestamp;
            isCurrentlyPlaying = false;
            lastPlayTimestamp = null;

            // Debounce pause event to filter buffering
            if (pauseDebounceTimer) {
                clearTimeout(pauseDebounceTimer);
            }

            pauseDebounceTimer = window.setTimeout(() => {
                pauseDebounceTimer = null;

                // Only send if we're still paused and have an active session
                if (!isCurrentlyPlaying && currentPlayback.value?.playHistoryId) {
                    whisperToServer('pause', {
                        play_history_id: currentPlayback.value.playHistoryId,
                        position: currentPlayback.value.position,
                    });
                }
            }, PAUSE_DEBOUNCE_MS);
        }
    }

    /**
     * Get the actual time spent playing in seconds.
     */
    function getActualPlayTimeSeconds(): number {
        let total = actualPlayTimeMs;
        if (isCurrentlyPlaying && lastPlayTimestamp !== null) {
            total += Date.now() - lastPlayTimestamp;
        }
        return Math.floor(total / 1000);
    }

    /**
     * Reset play time tracking for a new session.
     */
    function resetPlayTime() {
        actualPlayTimeMs = 0;
        lastPlayTimestamp = null;
        isCurrentlyPlaying = false;
        lastSyncedPosition = 0;
        justStartedSession = false;

        // Clear any pending pause debounce timer to prevent stale events
        if (pauseDebounceTimer) {
            clearTimeout(pauseDebounceTimer);
            pauseDebounceTimer = null;
        }
    }

    /**
     * Start tracking playback for a liveset.
     * Sends start event via WebSocket and waits for server to broadcast play_history_id.
     */
    async function startPlayback(livesetId: number, position: number = 0, quality?: string) {
        console.log('[PlaybackSync] startPlayback called:', { livesetId, position, quality });

        // End any existing playback first
        if (currentPlayback.value) {
            console.log('[PlaybackSync] Ending existing playback first');
            await endPlayback();
        }

        resetPlayTime();
        onPlay(); // Start tracking immediately

        // Join presence channel first
        console.log('[PlaybackSync] Joining presence channel...');
        await joinPresenceChannel();

        // Wait for connection
        if (!isConnected.value) {
            // Wait up to 3 seconds for connection
            await new Promise<void>((resolve) => {
                const checkInterval = setInterval(() => {
                    if (isConnected.value) {
                        clearInterval(checkInterval);
                        resolve();
                    }
                }, 100);
                setTimeout(() => {
                    clearInterval(checkInterval);
                    resolve();
                }, 3000);
            });
        }

        if (!isConnected.value) {
            console.error('[PlaybackSync] Failed to connect to presence channel');
            return;
        }

        // Set up temporary state
        currentPlayback.value = {
            livesetId,
            position,
            quality,
            playHistoryId: undefined,
        };

        // Create a promise that will be resolved when we receive the session.started event
        const playHistoryIdPromise = new Promise<number | null>((resolve) => {
            pendingStartResolve = resolve;

            // Timeout after 5 seconds
            setTimeout(() => {
                if (pendingStartResolve === resolve) {
                    pendingStartResolve = null;
                    console.error('[PlaybackSync] Timeout waiting for session.started event');
                    resolve(null);
                }
            }, 5000);
        });

        // Send start whisper
        console.log('[PlaybackSync] Sending start whisper');
        whisperToServer('start', {
            liveset_id: livesetId,
            position,
            quality,
            platform: 'web',
        });

        // Wait for server to broadcast session.started with play_history_id
        const playHistoryId = await playHistoryIdPromise;

        if (playHistoryId) {
            console.log('[PlaybackSync] Session started with play_history_id:', playHistoryId);
            justStartedSession = true;
            currentPlayback.value.playHistoryId = playHistoryId;
            lastSyncedPosition = position;
            sessionExpired.value = false;

            // Start periodic sync
            startSyncTimer();
        } else {
            console.error('[PlaybackSync] Failed to start session');
            currentPlayback.value = null;
        }
    }

    /**
     * Update local position (called frequently).
     * Detects seeks and sends them immediately via WebSocket.
     */
    function setPosition(position: number) {
        if (!currentPlayback.value) return;

        const previousPosition = currentPlayback.value.position;
        currentPlayback.value.position = position;

        // Detect seek: position jumped by more than threshold
        const positionDelta = Math.abs(position - previousPosition);
        if (positionDelta > SEEK_THRESHOLD_SECONDS && currentPlayback.value.playHistoryId) {
            console.log('[PlaybackSync] Seek detected:', previousPosition, '→', position, '(delta:', positionDelta, ')');
            whisperToServer('seek', {
                play_history_id: currentPlayback.value.playHistoryId,
                from_position: previousPosition,
                position: position,
            });
            lastSyncedPosition = position;
        }
    }

    /**
     * Update playback progress (sends to server via WebSocket).
     */
    async function updateProgress(position: number) {
        if (!currentPlayback.value?.playHistoryId) return;

        currentPlayback.value.position = position;
        lastSyncedPosition = position;

        const durationListened = getActualPlayTimeSeconds();

        whisperToServer('progress', {
            play_history_id: currentPlayback.value.playHistoryId,
            position,
            duration_listened: durationListened,
        });
    }

    /**
     * Change audio quality.
     */
    async function changeQuality(quality: string) {
        if (!currentPlayback.value?.playHistoryId) return;

        currentPlayback.value.quality = quality;

        whisperToServer('quality', {
            play_history_id: currentPlayback.value.playHistoryId,
            position: currentPlayback.value.position,
            quality,
        });
    }

    /**
     * End playback tracking.
     */
    async function endPlayback() {
        if (!currentPlayback.value?.playHistoryId) return;

        stopSyncTimer();
        onPause(); // Stop tracking play time

        const durationListened = getActualPlayTimeSeconds();

        whisperToServer('stop', {
            play_history_id: currentPlayback.value.playHistoryId,
            position: currentPlayback.value.position,
            duration_listened: durationListened,
        });

        currentPlayback.value = null;
        resetPlayTime();
        // Leave presence channel when playback ends
        leavePresenceChannel();
    }

    /**
     * Save current position for resume later (authenticated users only).
     * Still uses HTTP since it's not real-time critical.
     */
    async function savePosition(livesetId: number, position: number) {
        if (!isAuthenticated.value) return;

        const clientId = await getClientId().catch(() => undefined);

        try {
            await fetch(`/api/playback/positions/${livesetId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                    ...(clientId ? { 'X-Client-ID': clientId } : {}),
                },
                credentials: 'include',
                body: JSON.stringify({ position }),
            });
        } catch (error) {
            console.error('Failed to save position:', error);
        }
    }

    /**
     * Load saved positions for authenticated user.
     */
    async function loadPositions() {
        if (!isAuthenticated.value) return [];

        const clientId = await getClientId().catch(() => undefined);

        try {
            const response = await fetch('/api/playback/positions', {
                headers: {
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                    ...(clientId ? { 'X-Client-ID': clientId } : {}),
                },
                credentials: 'include',
            });

            if (response.ok) {
                const data = await response.json();
                return data.positions || [];
            }
        } catch (error) {
            console.error('Failed to load positions:', error);
        }

        return [];
    }

    /**
     * Clear saved position for a liveset.
     */
    async function clearPosition(livesetId: number) {
        if (!isAuthenticated.value) return;

        const clientId = await getClientId().catch(() => undefined);

        try {
            await fetch(`/api/playback/positions/${livesetId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                    ...(clientId ? { 'X-Client-ID': clientId } : {}),
                },
                credentials: 'include',
            });
        } catch (error) {
            console.error('Failed to clear position:', error);
        }
    }

    function startSyncTimer() {
        stopSyncTimer();
        syncTimer.value = window.setInterval(() => {
            if (currentPlayback.value) {
                updateProgress(currentPlayback.value.position);
            }
        }, SYNC_INTERVAL_MS);
    }

    function stopSyncTimer() {
        if (syncTimer.value) {
            clearInterval(syncTimer.value);
            syncTimer.value = null;
        }
    }

    function getCsrfToken(): string {
        const cookie = document.cookie
            .split('; ')
            .find(row => row.startsWith('XSRF-TOKEN='));
        return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
    }

    // Cleanup on unmount
    onUnmounted(() => {
        stopSyncTimer();
    });

    return {
        currentPlayback,
        isOnline,
        isConnected,
        sessionExpired,
        startPlayback,
        setPosition,
        updateProgress,
        endPlayback,
        changeQuality,
        savePosition,
        loadPositions,
        clearPosition,
        onPlay,
        onPause,
        getActualPlayTimeSeconds,
    };
}
