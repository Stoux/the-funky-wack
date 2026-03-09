import { ref } from 'vue';
import { echo } from '@laravel/echo-vue';
import { useAuth } from './useAuth';
import { getClientId } from './useDeviceId';

export interface LiveSession {
    channel_token: string;
    started_at: string;
    host: {
        name: string;
        user_id: number | null;
    };
    liveset: {
        id: number;
        title: string;
        artist_name: string;
        edition_number: string | null;
        duration_in_seconds: number | null;
    } | null;
    position: number;
    position_updated_at: string | null;
    quality: string | null;
    listeners_count: number;
}

// Module-level state (shared across components, same pattern as usePlaybackSync)

// As a listener
const isListeningAlong = ref(false);
const currentRoomToken = ref<string | null>(null);
const listenMode = ref<'synced' | 'independent'>('synced');
const hostName = ref('');

// As a host
const isHost = ref(false);
const listenerCount = ref(0);
const myRoomToken = ref<string | null>(null);

// Live page state
const liveSessions = ref<LiveSession[]>([]);
const totalListeners = ref(0);

let roomChannel: any = null;
let liveChannel: any = null;
let cachedClientId: string | null = null;

// Module-level callbacks (shared, only one registrant expected per event)
let seekCallback: ((position: number) => void) | null = null;
let pauseCallback: (() => void) | null = null;
let resumeCallback: (() => void) | null = null;
let trackChangeCallback: ((data: { liveset_id: number; position: number; quality: string }) => void) | null = null;
let hostStopCallback: (() => void) | null = null;

// Pre-fetch client ID on module load
getClientId().then(id => { cachedClientId = id; }).catch(() => {});

function getCsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find(row => row.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

export function useListenAlong() {
    const echoInstance = echo();
    const { user } = useAuth();

    /**
     * Fetch active sessions from the API.
     */
    async function fetchSessions(): Promise<void> {
        try {
            const response = await fetch('/api/live/sessions', {
                credentials: 'include',
            });
            if (response.ok) {
                const data = await response.json();
                liveSessions.value = data.sessions || [];
                totalListeners.value = liveSessions.value.reduce(
                    (sum, s) => sum + s.listeners_count, 0
                );
            }
        } catch (error) {
            console.error('[ListenAlong] Failed to fetch sessions:', error);
        }
    }

    /**
     * Join the global live presence channel for real-time session updates.
     */
    function joinLiveChannel(): void {
        if (liveChannel) return;

        liveChannel = echoInstance.join('live')
            .here(() => {
                console.log('[ListenAlong] Connected to live channel');
            })
            .listen('.sessions.updated', () => {
                fetchSessions();
            });
    }

    /**
     * Leave the global live presence channel.
     */
    function leaveLiveChannel(): void {
        if (liveChannel) {
            echoInstance.leave('live');
            liveChannel = null;
        }
    }

    /**
     * Join a listen-along room as a synced listener.
     */
    async function joinRoom(channelToken: string, mode: 'synced' | 'independent' = 'synced'): Promise<boolean> {
        try {
            const response = await fetch(`/api/live/rooms/${channelToken}/join`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                    ...(cachedClientId ? { 'X-Client-ID': cachedClientId } : {}),
                },
                credentials: 'include',
                body: JSON.stringify({ mode }),
            });

            if (!response.ok) {
                console.error('[ListenAlong] Failed to join room:', response.status);
                return false;
            }

            if (mode === 'independent') {
                // Independent mode: no WebSocket, just analytics
                return true;
            }

            // Synced mode: join the room's WebSocket channel
            currentRoomToken.value = channelToken;
            listenMode.value = mode;
            isListeningAlong.value = true;

            // Find the host name from live sessions
            const session = liveSessions.value.find(s => s.channel_token === channelToken);
            hostName.value = session?.host.name ?? 'Someone';

            joinRoomChannel(channelToken);

            return true;
        } catch (error) {
            console.error('[ListenAlong] Failed to join room:', error);
            return false;
        }
    }

    /**
     * Join the per-room WebSocket channel for host events.
     */
    function joinRoomChannel(channelToken: string): void {
        if (roomChannel) {
            leaveRoomChannel();
        }

        roomChannel = echoInstance.join(`listen-along.${channelToken}`)
            .here(() => {
                console.log('[ListenAlong] Connected to room channel');
            })
            .listen('.host.seek', (data: { position: number }) => {
                onHostSeek(data.position);
            })
            .listen('.host.pause', (data: { position: number }) => {
                onHostPause(data.position);
            })
            .listen('.host.resume', (data: { position: number }) => {
                onHostResume(data.position);
            })
            .listen('.host.track-change', (data: { liveset_id: number; position: number; quality: string }) => {
                onHostTrackChange(data);
            })
            .listen('.host.stop', () => {
                onHostStop();
            })
            .listen('.listener.joined', (data: { name: string; count: number }) => {
                if (isHost.value) {
                    listenerCount.value = data.count;
                }
            })
            .listen('.listener.left', (data: { count: number }) => {
                if (isHost.value) {
                    listenerCount.value = data.count;
                }
            });
    }

    function leaveRoomChannel(): void {
        if (roomChannel && currentRoomToken.value) {
            echoInstance.leave(`listen-along.${currentRoomToken.value}`);
            roomChannel = null;
        }
    }

    /**
     * Leave the current listen-along room.
     */
    async function leaveRoom(): Promise<void> {
        if (!currentRoomToken.value) return;

        try {
            await fetch(`/api/live/rooms/${currentRoomToken.value}/leave`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                    ...(cachedClientId ? { 'X-Client-ID': cachedClientId } : {}),
                },
                credentials: 'include',
            });
        } catch (error) {
            console.error('[ListenAlong] Failed to leave room:', error);
        }

        leaveRoomChannel();
        resetListenerState();
    }

    /**
     * Detach from synced mode to independent.
     */
    async function detach(): Promise<void> {
        if (!currentRoomToken.value) return;

        try {
            await fetch(`/api/live/rooms/${currentRoomToken.value}/detach`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                    ...(cachedClientId ? { 'X-Client-ID': cachedClientId } : {}),
                },
                credentials: 'include',
            });
        } catch (error) {
            console.error('[ListenAlong] Failed to detach:', error);
        }

        leaveRoomChannel();
        listenMode.value = 'independent';
        isListeningAlong.value = false;
        currentRoomToken.value = null;
    }

    function resetListenerState(): void {
        isListeningAlong.value = false;
        currentRoomToken.value = null;
        listenMode.value = 'synced';
        hostName.value = '';
    }

    // Host-side: set up when a room is created for this user
    function setupHost(channelToken: string): void {
        isHost.value = true;
        myRoomToken.value = channelToken;
        listenerCount.value = 0;

        // Join own room channel to listen for listener join/leave
        joinRoomChannel(channelToken);
    }

    function teardownHost(): void {
        if (myRoomToken.value) {
            leaveRoomChannel();
        }
        isHost.value = false;
        myRoomToken.value = null;
        listenerCount.value = 0;
    }

    // Event handlers — only fire if we're still actively listening along
    function onHostSeek(position: number): void {
        if (!isListeningAlong.value) return;
        console.log('[ListenAlong] Host seek to:', position);
        seekCallback?.(position);
    }

    function onHostPause(position: number): void {
        if (!isListeningAlong.value) return;
        console.log('[ListenAlong] Host paused at:', position);
        pauseCallback?.();
    }

    function onHostResume(position: number): void {
        if (!isListeningAlong.value) return;
        console.log('[ListenAlong] Host resumed at:', position);
        resumeCallback?.();
    }

    function onHostTrackChange(data: { liveset_id: number; position: number; quality: string }): void {
        if (!isListeningAlong.value) return;
        console.log('[ListenAlong] Host changed track:', data);
        trackChangeCallback?.(data);
    }

    function onHostStop(): void {
        console.log('[ListenAlong] Host stopped');
        hostStopCallback?.();
        resetListenerState();
    }

    function onSeek(callback: (position: number) => void): void {
        seekCallback = callback;
    }

    function onPause(callback: () => void): void {
        pauseCallback = callback;
    }

    function onResume(callback: () => void): void {
        resumeCallback = callback;
    }

    function onTrackChange(callback: (data: { liveset_id: number; position: number; quality: string }) => void): void {
        trackChangeCallback = callback;
    }

    function onHostStopped(callback: () => void): void {
        hostStopCallback = callback;
    }

    return {
        // Listener state
        isListeningAlong,
        currentRoomToken,
        listenMode,
        hostName,

        // Host state
        isHost,
        listenerCount,
        myRoomToken,

        // Live page state
        liveSessions,
        totalListeners,

        // Actions
        fetchSessions,
        joinLiveChannel,
        leaveLiveChannel,
        joinRoom,
        leaveRoom,
        detach,
        setupHost,
        teardownHost,

        // Event handlers (for audio player integration)
        onSeek,
        onPause,
        onResume,
        onTrackChange,
        onHostStopped,
    };
}
