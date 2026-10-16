import { computed, onBeforeUnmount, watch } from 'vue';
import { useAudioPlayer } from './useAudioPlayer';
import { useTracklistNowPlaying } from './useTracklistNowPlaying';
import { useQueue } from './useQueue';

// Sets are long, so skip further than a podcast player would
const SEEK_BACKWARD_SECONDS = 30;
const SEEK_FORWARD_SECONDS = 60;
// Further into a track than this, 'previous' restarts it instead of going to the previous track
const PREVIOUS_RESTART_THRESHOLD_SECONDS = 3;

const ACTIONS: MediaSessionAction[] = ['play', 'pause', 'previoustrack', 'nexttrack', 'seekbackward', 'seekforward', 'seekto'];

/**
 * Media keys, headphones and the lockscreen / notification player (Media Session API).
 * Next/previous jump between tracks of the tracklist, falling back to the next set.
 */
export function useMediaSession(controls: {
    play: () => void;
    pause: () => void;
    // Called before any external seek, e.g. to leave a synced listen-along session
    beforeSeek: () => void;
}) {
    if (typeof navigator === 'undefined' || !('mediaSession' in navigator)) {
        return;
    }

    const { currentLiveset, currentEdition, playing, currentTime, seek } = useAudioPlayer();
    const { nowPlayingSections, nowPlayingTracks, nowPlayingTrackIndices } = useTracklistNowPlaying();
    const { canAdvance, advance } = useQueue();

    const duration = computed(() => currentLiveset.value?.duration_in_seconds ?? 0);
    const hasTracklist = computed(() => nowPlayingSections.value.some(section => section.title));

    // During a transition both tracks are shown, like in the playbar: "A → B"
    const title = computed(() => {
        const titles = nowPlayingTracks.value.map(track => track.title).filter(Boolean);
        return titles.length ? titles.join(' → ') : currentLiveset.value?.title ?? '';
    });

    // Seeking from the lockscreen or headphones while paused stays paused, like any other player
    function seekTo(seconds: number) {
        controls.beforeSeek();
        seek(Math.floor(Math.max(0, Math.min(seconds, duration.value || seconds))), { keepPaused: true });
    }

    function nextTrack() {
        const sections = nowPlayingSections.value;
        const indices = nowPlayingTrackIndices.value;
        // In a transition skip to the incoming track, not past it
        const target = indices.length === 2 ? indices[1] : (indices[0] ?? 0) + 1;

        if (hasTracklist.value && target < sections.length) {
            seekTo(sections[target].start_at);
            return;
        }
        // Nothing to skip to: don't leave a listen-along session for nothing
        if (!canAdvance.value) {
            return;
        }
        controls.beforeSeek();
        advance();
    }

    function previousTrack() {
        const sections = nowPlayingSections.value;
        const index = nowPlayingTrackIndices.value[0] ?? 0;
        const current = sections[index];

        if (!current || index === 0 || currentTime.value - current.start_at > PREVIOUS_RESTART_THRESHOLD_SECONDS) {
            seekTo(current?.start_at ?? 0);
            return;
        }
        seekTo(sections[index - 1].start_at);
    }

    const handlers: Partial<Record<MediaSessionAction, MediaSessionActionHandler>> = {
        play: () => controls.play(),
        pause: () => controls.pause(),
        previoustrack: () => previousTrack(),
        nexttrack: () => nextTrack(),
        seekbackward: (details) => seekTo(currentTime.value - (details.seekOffset ?? SEEK_BACKWARD_SECONDS)),
        seekforward: (details) => seekTo(currentTime.value + (details.seekOffset ?? SEEK_FORWARD_SECONDS)),
        seekto: (details) => seekTo(details.seekTime ?? currentTime.value),
    };

    for (const action of ACTIONS) {
        try {
            navigator.mediaSession.setActionHandler(action, handlers[action] ?? null);
        } catch {
            // Action not supported by this browser
        }
    }

    watch([title, currentLiveset, currentEdition], () => {
        const liveset = currentLiveset.value;
        if (!liveset) {
            navigator.mediaSession.metadata = null;
            return;
        }

        // Posters keep their aspect ratio and only the width is known, so no `sizes`: largest first wins
        const posters = [...(currentEdition.value?.poster_srcset_urls ?? [])].sort((a, b) => b.width - a.width);
        navigator.mediaSession.metadata = new MediaMetadata({
            title: title.value,
            artist: liveset.artist_name,
            album: `${liveset.title} · TFW #${currentEdition.value?.number ?? '?'}`,
            artwork: posters.length
                ? posters.map(poster => ({ src: poster.url }))
                : [{ src: '/android-chrome-512x512.png', sizes: '512x512', type: 'image/png' }],
        });
    }, { immediate: true });

    function updatePositionState() {
        if (!('setPositionState' in navigator.mediaSession)) {
            return;
        }
        try {
            navigator.mediaSession.setPositionState(duration.value ? {
                duration: duration.value,
                position: Math.min(currentTime.value, duration.value),
                playbackRate: 1,
            } : undefined);
        } catch {
            // Invalid state while switching sets, the next update fixes it
        }
    }

    watch(playing, (isPlaying) => {
        navigator.mediaSession.playbackState = isPlaying ? 'playing' : 'paused';
        updatePositionState();
    }, { immediate: true });

    // The OS extrapolates the position while playing, so only report set changes and seeks, not every tick
    watch(duration, () => updatePositionState(), { immediate: true });
    watch(currentTime, (time, previousTime) => {
        if (Math.abs(time - (previousTime ?? 0)) > 1.5) {
            updatePositionState();
        }
    });

    onBeforeUnmount(() => {
        for (const action of ACTIONS) {
            try {
                navigator.mediaSession.setActionHandler(action, null);
            } catch {
                // Action not supported by this browser
            }
        }
    });
}
