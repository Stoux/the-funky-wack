<script setup lang="ts">

import {DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger} from "@/components/ui/dropdown-menu";
import {ChevronDown, Pause, Play, Loader2, Volume2, VolumeX, Users, Unlink} from "lucide-vue-next";
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
    initPlayer,
    playLiveset,
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
                        playLiveset(result.edition, result.liveset, state.quality as any, position);
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

onMounted(() => {
    if (waveformContainer.value && audioElement.value) {
        mount(waveformContainer.value, audioElement.value);
        // Apply saved volume
        (audioElement.value as HTMLAudioElement).volume = volume.value;
    }

    // Wire up host pause dialog for synced listeners
    onListenAlongPause(() => {
        if (isListeningAlong.value) {
            showHostPauseDialog.value = true;
        }
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
        play();
    });

    // Wire up host track change — load the new liveset
    onListenAlongTrackChange((data) => {
        const result = findLivesetById(data.liveset_id);
        if (result) {
            playLiveset(result.edition, result.liveset, data.quality as any, data.position);
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

function handleHostPause(): void {
    // Pause with host
    if (playing.value) {
        togglePlayPause();
    }
}

function handleHostDetach(): void {
    // Already detached by the dialog component
}

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
            <div class="h-32 bg-muted rounded-md relative" ref="waveform">
                <!-- Intercept clicks when synced to prevent accidental seeks -->
                <div
                    v-if="isListeningAlong"
                    class="absolute inset-0 z-10 cursor-pointer"
                    @click.stop.prevent="handleWaveformClick"
                />
            </div>
            <audio ref="audio" />
            <div class="flex justify-between text-xs text-muted-foreground mt-1">
                <span>{{ formatDuration(currentTime) }}</span>
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
        <HostPauseDialog
            v-model:open="showHostPauseDialog"
            @pause="handleHostPause"
            @detach="handleHostDetach"
        />

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
