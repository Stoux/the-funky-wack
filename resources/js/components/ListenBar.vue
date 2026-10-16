<script setup lang="ts">

import {DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger} from "@/components/ui/dropdown-menu";
import {ChevronDown, Pause, Play, Loader2, Volume2, VolumeX, Users, Unlink, ZoomIn, ZoomOut, LocateFixed} from "lucide-vue-next";
import {Button} from "@/components/ui/button";
import {onBeforeUnmount, onMounted, ref, useTemplateRef, watch} from "vue";
import {formatDuration} from "@/lib/utils";
import LivesetTrackList from "@/components/LivesetTrackList.vue";
import LivesetDescription from "@/components/LivesetDescription.vue";
import CastButton from "@/components/CastButton.vue";
import ShareLivesetButton from "@/components/ShareLivesetButton.vue";
import QueuePanel from "@/components/QueuePanel.vue";
import HostPauseDialog from "@/components/HostPauseDialog.vue";
import HostStoppedDialog from "@/components/HostStoppedDialog.vue";
import ListenerSeekDialog from "@/components/ListenerSeekDialog.vue";
import ListenerResumeDialog from "@/components/ListenerResumeDialog.vue";
import CommentsSheet from "@/components/CommentsSheet.vue";
import CommentBubble from "@/components/CommentBubble.vue";
import WaveformCommentMarkers from "@/components/WaveformCommentMarkers.vue";
import {useComments} from "@/composables/useComments";
import {useMediaSession} from "@/composables/useMediaSession";
import {useAudioPlayer} from "@/composables/useAudioPlayer";
import {useListenAlong} from "@/composables/useListenAlong";
import {useEditions} from "@/composables/useEditions";

const {
    currentLiveset,
    currentEdition,
    quality,
    loading,
    playing,
    currentTime,
    hasPeaks,
    generatePeaksIfMissing,
    availableQualities,
    qualityLabels,
    nowPlayingTrack,
    nowPlayingTracks,
    mount,
    unmount,
    togglePlayPause,
    play,
    pause,
    seek,
    setQuality,
    toggleGeneratePeaks,
    playLiveset,
    zoomLevel,
    maxZoom,
    followPlayhead,
    setZoom,
    setFollowPlayhead,
} = useAudioPlayer();

const { findLivesetById } = useEditions();

const {
    isListeningAlong,
    currentRoomToken,
    hostName,
    syncPaused,
    isHost,
    listenerCount,
    detach,
    pauseSyncState,
    resumeSyncState,
    onPause: onListenAlongPause,
    onResume: onListenAlongResume,
    onSeek: onListenAlongSeek,
    onTrackChange: onListenAlongTrackChange,
    onHostStopped,
} = useListenAlong();

const waveformContainer = useTemplateRef('waveform');
const audioElement = useTemplateRef('audio');
const commentsSheet = useTemplateRef('commentsSheet');

// Waveform zoom: buttons, ctrl/cmd + wheel (Chrome/Firefox trackpad pinch), Safari gesture events and touch pinch
const ZOOM_STEP = 1.5;
let pinch: { distance: number; zoom: number } | null = null;
let gestureStartZoom = 1;
// Wheel and pinch fire many events per frame, every zoom re-renders the waveform, so apply only the latest per frame
let pendingZoom: { level: number; anchorX?: number } | null = null;

function requestZoom(level: number, anchorX?: number): void {
    const isScheduled = pendingZoom !== null;
    pendingZoom = { level, anchorX };
    if (isScheduled) {
        return;
    }
    requestAnimationFrame(() => {
        if (pendingZoom) {
            setZoom(pendingZoom.level, pendingZoom.anchorX);
        }
        pendingZoom = null;
    });
}

function waveformOffsetX(clientX: number): number {
    return clientX - (waveformContainer.value?.getBoundingClientRect().left ?? 0);
}

function touchDistance(touches: TouchList): number {
    return Math.hypot(touches[0].clientX - touches[1].clientX, touches[0].clientY - touches[1].clientY);
}

/**
 * Panning a zoomed waveform means looking elsewhere, so stop following the playhead.
 * Not while listening along: the click overlay sits on top there, so the waveform can't pan anyway.
 */
function stopFollowingOnPan(): void {
    if (zoomLevel.value > 1 && !isListeningAlong.value) {
        setFollowPlayhead(false);
    }
}

function handleWaveformWheel(event: WheelEvent): void {
    if (event.ctrlKey || event.metaKey) {
        event.preventDefault();
        // Firefox mouse wheels report lines or pages instead of pixels
        const deltaPx = event.deltaY * (event.deltaMode === WheelEvent.DOM_DELTA_LINE ? 16 : event.deltaMode === WheelEvent.DOM_DELTA_PAGE ? window.innerHeight : 1);
        const baseZoom = pendingZoom?.level ?? zoomLevel.value;
        requestZoom(baseZoom * Math.exp(-deltaPx * 0.01), waveformOffsetX(event.clientX));
        return;
    }
    if (event.shiftKey || Math.abs(event.deltaX) > Math.abs(event.deltaY)) {
        stopFollowingOnPan();
    }
}

// Safari sends a trackpad pinch as gesture events (with a scale) instead of ctrl + wheel.
// iOS fires them next to the touch events of a pinch, the touch handlers own that one.
function handleWaveformGestureStart(event: Event): void {
    event.preventDefault();
    gestureStartZoom = zoomLevel.value;
}

function handleWaveformGestureChange(event: Event & { scale?: number; clientX?: number }): void {
    event.preventDefault();
    if (pinch) {
        return;
    }
    // Without a position zoom like the buttons do, around the playhead or the middle
    requestZoom(gestureStartZoom * (event.scale ?? 1), event.clientX === undefined ? undefined : waveformOffsetX(event.clientX));
}

function handleWaveformTouchStart(event: TouchEvent): void {
    pinch = event.touches.length === 2 ? { distance: touchDistance(event.touches), zoom: zoomLevel.value } : null;
}

function handleWaveformTouchEnd(event: TouchEvent): void {
    if (event.touches.length < 2) {
        pinch = null;
    }
}

function handleWaveformTouchMove(event: TouchEvent): void {
    if (pinch && event.touches.length === 2) {
        event.preventDefault();
        const centroidX = (event.touches[0].clientX + event.touches[1].clientX) / 2;
        requestZoom(pinch.zoom * touchDistance(event.touches) / pinch.distance, waveformOffsetX(centroidX));
        return;
    }
    // One finger drags the zoomed waveform (native scroll)
    stopFollowingOnPan();
}

// Comments for the playing set (waveform markers + bubble), followed live
const { ensureLoaded: ensureCommentsLoaded, subscribeToLiveset, unsubscribeFromLiveset } = useComments();
watch(() => currentLiveset.value?.id, (livesetId, previousLivesetId) => {
    if (previousLivesetId) {
        unsubscribeFromLiveset(previousLivesetId);
    }
    if (livesetId) {
        ensureCommentsLoaded(livesetId);
        subscribeToLiveset(livesetId);
    }
}, { immediate: true });
onBeforeUnmount(() => {
    if (currentLiveset.value) {
        unsubscribeFromLiveset(currentLiveset.value.id);
    }
});

// Volume control
const VOLUME_STORAGE_KEY = 'tfw-volume';
const volume = ref(parseFloat(localStorage.getItem(VOLUME_STORAGE_KEY) ?? '1'));
const isMuted = ref(false);
let previousVolume = volume.value;

// Dialogs
const showHostPauseDialog = ref(false);
const showHostStoppedDialog = ref(false);
const stoppedHostName = ref('');
const showSeekDesyncDialog = ref(false);
const showResumeDialog = ref(false);

function setVolume(newVolume: number): void {
    if (newVolume > 0) {
        previousVolume = newVolume;
    }
    volume.value = newVolume;
    isMuted.value = newVolume === 0;
    localStorage.setItem(VOLUME_STORAGE_KEY, String(newVolume));
    if (audioElement.value) {
        (audioElement.value as HTMLAudioElement).volume = newVolume;
    }
}

function toggleMute(): void {
    if (isMuted.value) {
        setVolume(previousVolume || 0.5);
        isMuted.value = false;
    } else {
        previousVolume = volume.value;
        setVolume(0);
        isMuted.value = true;
    }
}

/**
 * Intercept play/pause for synced listeners.
 * - Pause: pause sync so host events are ignored while paused
 * - Play while sync paused: show resume dialog instead of just playing
 */
function handlePlayPause(): void {
    if (isListeningAlong.value && !syncPaused.value && playing.value) {
        // Synced listener pressing pause — pause sync
        pauseSyncState();
        togglePlayPause();
        return;
    }

    if (isListeningAlong.value && syncPaused.value && !playing.value) {
        // Synced listener pressing play after pausing — show resume dialog
        showResumeDialog.value = true;
        return;
    }

    togglePlayPause();
}

async function handleResync(): Promise<void> {
    const token = currentRoomToken.value;
    resumeSyncState();

    // Fetch host's current state and sync to it
    if (token) {
        try {
            const res = await fetch(`/api/live/rooms/${token}/state`, { credentials: 'include' });
            if (res.ok) {
                const state = await res.json();
                const updatedAt = state.position_updated_at ? new Date(state.position_updated_at).getTime() : 0;
                const elapsed = updatedAt ? Math.max(0, (Date.now() - updatedAt) / 1000) : 0;
                const position = Math.floor(state.position + elapsed);

                // Host may have switched tracks while we were paused
                if (state.liveset?.id && state.liveset.id !== currentLiveset.value?.id) {
                    const result = findLivesetById(state.liveset.id);
                    if (result) {
                        playLiveset(result.edition, result.liveset, undefined, position);
                        return;
                    }
                }

                seek(position);
            }
        } catch { /* fall through to just play */ }
    }

    play();
}

function handleResumeDetach(): void {
    detach();
    play();
}

// Media keys, headphones and the lockscreen player follow the same listen-along rules as the button here.
// Skipping or seeking from outside the app leaves a synced session, like on Android.
useMediaSession({
    play: () => {
        if (playing.value) {
            return;
        }
        // The resume dialog is useless on a lockscreen or in a background tab, so go straight back to the host
        if (isListeningAlong.value && syncPaused.value && document.visibilityState === 'hidden') {
            handleResync();
            return;
        }
        handlePlayPause();
    },
    pause: () => {
        if (playing.value) {
            handlePlayPause();
        }
    },
    beforeSeek: () => {
        if (isListeningAlong.value) {
            detach();
        }
    },
});

onMounted(() => {
    if (waveformContainer.value && audioElement.value) {
        mount(waveformContainer.value, audioElement.value);
        // Apply saved volume
        (audioElement.value as HTMLAudioElement).volume = volume.value;
    }

    // Wire up host pause dialog for synced listeners — pause locally and prompt
    onListenAlongPause(() => {
        if (!isListeningAlong.value) {
            return;
        }
        if (playing.value) {
            pause();
        }
        showHostPauseDialog.value = true;
    });

    // Wire up host stopped notification
    onHostStopped(() => {
        stoppedHostName.value = hostName.value || 'The host';
        showHostStoppedDialog.value = true;
    });

    // Wire up synced playback controls
    onListenAlongSeek((position: number) => {
        seek(position);
    });

    onListenAlongResume(() => {
        // Host resumed — close the pause dialog if it's still up and resume playback
        showHostPauseDialog.value = false;
        play();
    });

    // Wire up host track change — load the new liveset. The listener keeps its own quality setting
    onListenAlongTrackChange((data) => {
        // Host restarted its session on the same set (e.g. session expiry). Only resync the
        // position, playLiveset would toggle playback for the liveset that's already loaded
        if (data.liveset_id === currentLiveset.value?.id) {
            seek(data.position);
            return;
        }

        const result = findLivesetById(data.liveset_id);
        if (result) {
            playLiveset(result.edition, result.liveset, undefined, data.position);
        }
    });
});

onBeforeUnmount(() => {
    unmount();
});

// Keep audio volume in sync
watch(volume, (v) => {
    if (audioElement.value) {
        (audioElement.value as HTMLAudioElement).volume = v;
    }
});

function handleSeekDesync(): void {
    detach();
    showSeekDesyncDialog.value = false;
}

function handleWaveformClick(): void {
    if (isListeningAlong.value) {
        showSeekDesyncDialog.value = true;
    }
}

</script>

<template>
    <div class="fixed bottom-0 left-0 right-0 bg-background border-t p-4 flex flex-col gap-4 z-50">

        <div class="flex-1">
            <div
                class="h-32 bg-muted rounded-md relative touch-pan-x"
                ref="waveform"
                @wheel="handleWaveformWheel"
                @touchstart="handleWaveformTouchStart"
                @touchmove="handleWaveformTouchMove"
                @touchend="handleWaveformTouchEnd"
                @touchcancel="handleWaveformTouchEnd"
                @gesturestart="handleWaveformGestureStart"
                @gesturechange="handleWaveformGestureChange"
            >
                <!-- Intercept clicks when synced to prevent accidental seeks -->
                <div
                    v-if="isListeningAlong"
                    class="absolute inset-0 z-10 cursor-pointer"
                    @click.stop.prevent="handleWaveformClick"
                />
                <template v-if="currentLiveset">
                    <WaveformCommentMarkers
                        :liveset-id="currentLiveset.id"
                        :duration-seconds="currentLiveset.duration_in_seconds ?? 0"
                        @open-comments="commentsSheet?.open()"
                    />
                    <CommentBubble :liveset-id="currentLiveset.id" :current-time="currentTime" />
                </template>
            </div>
            <audio ref="audio" />
            <div class="flex justify-between items-center text-xs text-muted-foreground mt-1">
                <span>{{ formatDuration(currentTime) }}</span>
                <div class="flex items-center gap-1">
                    <button class="p-1 rounded cursor-pointer hover:text-foreground disabled:opacity-40 disabled:cursor-default" title="Zoom out" :disabled="zoomLevel <= 1" @click="setZoom(zoomLevel / ZOOM_STEP)">
                        <ZoomOut class="h-3.5 w-3.5" />
                    </button>
                    <button class="px-1 rounded font-mono tabular-nums cursor-pointer hover:text-foreground disabled:cursor-default" title="Reset zoom" :disabled="zoomLevel <= 1" @click="setZoom(1)">
                        {{ zoomLevel.toFixed(1) }}×
                    </button>
                    <button class="p-1 rounded cursor-pointer hover:text-foreground disabled:opacity-40 disabled:cursor-default" title="Zoom in" :disabled="zoomLevel >= maxZoom" @click="setZoom(zoomLevel * ZOOM_STEP)">
                        <ZoomIn class="h-3.5 w-3.5" />
                    </button>
                    <button v-if="zoomLevel > 1 && !followPlayhead" class="flex items-center gap-1 px-1 rounded cursor-pointer text-primary hover:underline" title="Follow the playhead again" @click="setFollowPlayhead(true)">
                        <LocateFixed class="h-3.5 w-3.5" />
                        Follow
                    </button>
                </div>
                <span>{{ formatDuration(currentLiveset?.duration_in_seconds ?? 0) }}</span>
            </div>
        </div>

        <div class="flex items-center space-x-4 w-full">
            <Button size="icon" variant="ghost" class="h-8 w-8 rounded-full" @click.prevent="handlePlayPause">
                <Loader2 class="w-4 h-4 animate-spin" v-if="loading" />
                <Pause class="h-4 w-4" v-else-if="playing" />
                <Play class="h-4 w-4" v-else />
            </Button>

            <div class="flex-1">
                <div class="text-sm">
                    <div class="font-medium">{{ currentLiveset?.title }}</div>
                    <div class="text-red-600" v-if="loading && hasPeaks === false && generatePeaksIfMissing">No peaks available. Loading full audio file to generate waveform.
                        <span class="underline cursor-pointer" @click="toggleGeneratePeaks">Disable?</span>
                    </div>
                    <div class="text-muted-foreground" v-else>{{ currentLiveset?.artist_name }} • TFW #{{ currentEdition?.number }}</div>
                </div>

                <!-- Listen-along badge -->
                <div v-if="isListeningAlong" class="flex items-center space-x-2 text-xs text-primary mt-1">
                    <span>Listening with {{ hostName }}</span>
                    <span v-if="syncPaused" class="text-muted-foreground">(paused)</span>
                    <button class="flex items-center space-x-1 text-muted-foreground hover:text-foreground transition-colors" @click="detach" title="Detach and play independently">
                        <Unlink class="h-3 w-3" />
                        <span>Detach</span>
                    </button>
                </div>

                <!-- Host badge -->
                <div v-else-if="isHost && listenerCount > 0" class="flex items-center space-x-1 text-xs text-primary mt-1">
                    <Users class="h-3 w-3" />
                    <span>{{ listenerCount }} listening</span>
                </div>
            </div>

            <div class="text-sm text-right hidden lg:block" v-if="currentLiveset?.tracks?.length">
                <div class="font-medium">Now playing</div>
                <div class="text-muted-foreground" v-if="nowPlayingTracks.length >= 2">
                    {{ nowPlayingTracks[0]?.title || '?' }} → {{ nowPlayingTracks[1]?.title || '?' }}
                </div>
                <div class="text-muted-foreground" v-else>{{ nowPlayingTrack?.title || '?' }}</div>
            </div>

            <Button variant="destructive" v-if="!loading && hasPeaks === false && !generatePeaksIfMissing" @click="toggleGeneratePeaks"
                    title="This liveset has no waveform data. Load the full audio file to generate waveform locally?">
                Generate waveform?
            </Button>

            <!-- Volume control -->
            <div class="hidden sm:flex items-center space-x-2">
                <Button size="icon" variant="ghost" class="h-8 w-8" @click="toggleMute">
                    <VolumeX class="h-4 w-4" v-if="isMuted || volume === 0" />
                    <Volume2 class="h-4 w-4" v-else />
                </Button>
                <input
                    type="range"
                    min="0"
                    max="1"
                    step="0.01"
                    :value="volume"
                    @input="setVolume(parseFloat(($event.target as HTMLInputElement).value))"
                    class="w-20 h-1 accent-primary cursor-pointer"
                />
            </div>

            <div class="flex items-center space-x-1">
                <CastButton />

                <QueuePanel />

                <LivesetTrackList :liveset="currentLiveset!" :current-time="currentTime" button-type="ghost" v-if="currentLiveset?.tracks?.length" />

                <!-- Hidden on mobile for space, the waveform markers still open it -->
                <CommentsSheet ref="commentsSheet" :liveset="currentLiveset!" button-type="ghost" trigger-class="hidden sm:inline-flex" />

                <LivesetDescription :edition="currentEdition!" :liveset="currentLiveset!" button-type="ghost" v-if="currentLiveset?.description" />

                <ShareLivesetButton :edition="currentEdition!" :liveset="currentLiveset!" button-type="ghost" />
            </div>

            <DropdownMenu>
                <DropdownMenuTrigger as="div">
                    <Button variant="outline" size="sm" class="flex items-center gap-1">
                        {{ qualityLabels[quality] }}
                        <ChevronDown class="h-4 w-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent>
                    <DropdownMenuItem v-for="value of availableQualities" @click="setQuality(value)" :key="value">
                        {{ qualityLabels[value] }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <!-- Host pause dialog (for synced listeners) -->
        <HostPauseDialog v-model:open="showHostPauseDialog" @leave="play" />

        <!-- Host stopped notification -->
        <HostStoppedDialog
            v-model:open="showHostStoppedDialog"
            :host-name="stoppedHostName"
        />

        <!-- Listener seek desync dialog -->
        <ListenerSeekDialog
            v-model:open="showSeekDesyncDialog"
            @detach="handleSeekDesync"
            @cancel="showSeekDesyncDialog = false"
        />

        <!-- Listener resume dialog (after pausing while synced) -->
        <ListenerResumeDialog
            v-model:open="showResumeDialog"
            :host-name="hostName"
            @resync="handleResync"
            @detach="handleResumeDetach"
        />
    </div>
</template>

<style scoped>

</style>
