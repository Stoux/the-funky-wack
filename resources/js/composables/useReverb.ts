import { ref, onUnmounted } from 'vue';
import { useEcho, useEchoModel } from '@laravel/echo-vue';
import type { User } from '@/types';

/**
 * Composable for managing Reverb WebSocket connections and channels.
 */
export function useReverb() {
    const echo = useEcho();

    /**
     * Join a private channel for the authenticated user.
     */
    function joinUserChannel(userId: number) {
        return echo.private(`user.${userId}`);
    }

    /**
     * Join a session channel for playback tracking.
     */
    function joinSessionChannel(sessionId: string) {
        return echo.private(`session.${sessionId}`);
    }

    /**
     * Join a presence channel to track live listeners.
     */
    function joinPresenceChannel(channelName: string) {
        return echo.join(channelName);
    }

    /**
     * Leave a channel.
     */
    function leaveChannel(channelName: string) {
        echo.leave(channelName);
    }

    return {
        echo,
        joinUserChannel,
        joinSessionChannel,
        joinPresenceChannel,
        leaveChannel,
    };
}

/**
 * Composable for tracking live listener counts on a liveset.
 */
export function useLiveListeners(livesetId: number) {
    const listenerCount = ref(0);
    const listeners = ref<User[]>([]);

    const { echo } = useReverb();

    const channel = echo.join(`liveset.${livesetId}`)
        .here((users: User[]) => {
            listeners.value = users;
            listenerCount.value = users.length;
        })
        .joining((user: User) => {
            listeners.value.push(user);
            listenerCount.value++;
        })
        .leaving((user: User) => {
            listeners.value = listeners.value.filter(u => u.id !== user.id);
            listenerCount.value--;
        });

    onUnmounted(() => {
        echo.leave(`liveset.${livesetId}`);
    });

    return {
        listenerCount,
        listeners,
    };
}
