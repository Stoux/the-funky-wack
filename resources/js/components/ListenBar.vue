<script setup lang="ts">

import {DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger} from "@/components/ui/dropdown-menu";
import {ChevronDown, Pause, Play, Loader2} from "lucide-vue-next";
import {Button} from "@/components/ui/button";
import WaveSurfer from "wavesurfer.js";
import {computed, nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef, watch} from "vue";
import {Edition, Liveset, LivesetFilesByQuality, LivesetQuality} from "@/types";
import {formatDuration} from "@/lib/utils";
import LivesetTrackList from "@/components/LivesetTrackList.vue";
import LivesetDescription from "@/components/LivesetDescription.vue";
import {useNowPlayingState} from "@/composables/useNowPlayingState";
import CastButton from "@/components/CastButton.vue";
import {useCastMedia} from "@/composables/useCastMedia";
import HoverPlugin from "wavesurfer.js/plugins/hover";
import {useTracklistNowPlaying} from "@/composables/useTracklistNowPlaying";
import {determineNowPlayingTrack} from "@/lib/tracklist.utils";

const props = defineProps<{
    edition: Edition,
    liveset: Liveset,
    // Not actual files but labels
    qualities: LivesetFilesByQuality,
}>();

const {
    audioQuality: quality,
    loading,
    playing,
    finished,
    currentTime,
} = useNowPlayingState();

const {
    nowPlayingSections,
    nowPlayingTrack,
} = useTracklistNowPlaying();

const castMedia = useCastMedia();
const audioElement = useTemplateRef('audio');

let waveInstance: WaveSurfer|undefined = undefined;
const loadingSource = ref<string|undefined>(undefined);
const hasPeaks = ref<boolean|undefined>(undefined);
const generatePeaksIfMissing = ref(false);

const availableQualities = computed<LivesetQuality[]>(() => {
    const keys = Object.keys(props.qualities) as LivesetQuality[];
    return keys.filter(quality => props.liveset.files?.[quality] !== undefined);
});

const source = computed<string|undefined>(() => {
    return props.liveset.files?.[quality.value] ?? undefined;
});

// Switch to the first available quality if the current one is not available for the new liveset
watch(() => props.liveset, () => {
    nextTick(() => {
        checkAvailableQuality();
    });
})

watch(source, () => {
    initPlayer();
});

watch(playing, shouldBePlaying => {
    // Early abort if no wavesurfer, still loading the track or we're already in that isPlaying state
    if (!waveInstance || loading.value || shouldBePlaying === waveInstance.isPlaying()) {
        return;
    }

    // Note: the isPlaying === what we should have as state
    if (shouldBePlaying) {
        waveInstance.play();
    } else {
        waveInstance.pause();
    }
});

watch(currentTime, shouldBeAtTime => {
    // Early abort if no wavesurfer, still loading the track or we're already (or almost) at that time
    if (!waveInstance || loading.value || Math.abs(waveInstance.getCurrentTime() - shouldBeAtTime) <= 2) {
        return;
    }

    waveInstance.play(shouldBeAtTime);
})

function checkAvailableQuality() {
    if (source.value) {
        return;
    }

    console.log('Switching quality', quality.value);
    if (availableQualities.value.includes('hq')) {
        quality.value = 'hq';
    } else if ( availableQualities.value.includes('lossless')) {
        quality.value = 'lossless';
    } else if (availableQualities.value.includes('lq')) {
        quality.value = 'lq';
    }
    console.log('New quality', quality.value);
}

async function initPlayer() {
    // Check if not already loading this source
    if (source.value && source.value === loadingSource.value) {
        return;
    }

    // Destroy the old instance
    waveInstance?.destroy();
    if (!source.value) {
        console.log('No source');
        return;
    }

    playing.value = false;
    finished.value = false;
    loading.value = true;
    loadingSource.value = source.value;

    // Super hacky: Disable remote playback on the audio element to force the remote API to realize the source URL has changed, otherwise it will just play the previous liveset :').
    if (castMedia.casting.value && audioElement.value) {
        castMedia.casting.value = 'reconnecting';
        audioElement.value.disableRemotePlayback = true;
    }

    // Load peaks if they are available
    hasPeaks.value = undefined;
    let peaks = generatePeaksIfMissing.value ? undefined : [ [] ];
    if (props.liveset.audio_waveform_url) {
        const peaksForSource = source.value;
        try {
            const peakData: { data: any[] } = await fetch(props.liveset.audio_waveform_url ?? '').then(response => response.json())
            peaks = peakData.data;
            hasPeaks.value = true;
        } catch (e: any) {
            // Failed.
            console.log('Failed to load waveform peaks', e);
        }

        if (peaksForSource !== source.value) {
            // Source changed! Abort loading
            return;
        }
    }
    if (hasPeaks.value === undefined) {
        hasPeaks.value = false;
    }

    let lastShownTrackIndex: number|undefined = undefined;

    const surfer = waveInstance = WaveSurfer.create({
        container: '#waveform',
        barWidth: 1,
        barHeight: 1, // the height of the wave
        barGap: 2,
        barAlign: 'bottom',
        progressColor: '#57ECED',
        waveColor: '#B4B7BC',
        height: 128,
        normalize: true,
        mediaControls: false,
        hideScrollbar: true,
        autoCenter: false,
        minPxPerSec: 1,
        peaks: peaks,
        url: source.value,
        media: audioElement.value ?? undefined,
        plugins: [
            HoverPlugin.create({
                lineColor: '#ff0000',
                lineWidth: 2,
                labelBackground: '#555',
                labelColor: '#fff',
                labelSize: '11px',
                formatTimeCallback: (seconds) => {
                    seconds = Math.floor(seconds);

                    lastShownTrackIndex = determineNowPlayingTrack(
                        nowPlayingSections.value,
                        seconds,
                        lastShownTrackIndex,
                    );

                    // Transform into a duration timestamp (hh:mm:ss)
                    const duration = formatDuration(seconds);
                    if (lastShownTrackIndex === undefined) {
                        return duration;
                    }

                    // We actually do know the track
                    const track = nowPlayingSections.value[lastShownTrackIndex];
                    return `${duration} | ${track.title}`;
                },
            })
        ]
    })

    surfer.on('click', () => {
        surfer.play()
    })

    surfer.on('play', () => {
        playing.value = true;
    })
    surfer.on('pause', () => {
        playing.value = false;
    })

    surfer.on('ready', () => {
        loading.value = false;

        // Super hacky: Re-enable remote playback on the audio element & force another prompt.
        if (castMedia.casting.value === 'reconnecting' && audioElement.value) {
            audioElement.value.disableRemotePlayback = false;
            castMedia.casting.value = 'connected';
            // Prompts the thing right into your face again, but it does force the cast session to actually update the source.
            castMedia.promptForCast()?.then(() => {
                audioElement.value?.play();
            })
        }

        // PLay the track at the last configured time (probably 0 if new liveset or a given timestamp when restoring / playing specific track)
        surfer.play(currentTime.value);

        castMedia.withAudioElement(audioElement.value ?? undefined);
    })

    surfer.on('finish', () => {
        finished.value = true;
        playing.value = false;
    })

    surfer.on('timeupdate', (time) => {
        if (loading.value) {
            // Ignore initial state updates when loading
            return;
        }

        currentTime.value = Math.floor( time );
    })
}

onMounted(() => {
    checkAvailableQuality();
    initPlayer();
});


onBeforeUnmount(() => {
    waveInstance?.destroy();
    waveInstance = undefined;
})



</script>

<template>
    <div class="fixed bottom-0 left-0 right-0 bg-background border-t p-4 flex flex-col gap-4 z-50">

        <div class="flex-1">
            <div class="h-32 bg-muted rounded-md relative" id="waveform">

            </div>
            <audio ref="audio" />
            <div class="flex justify-between text-xs text-muted-foreground mt-1">
                <span>{{ formatDuration(currentTime) }}</span>
                <span>{{ formatDuration(liveset.duration_in_seconds) }}</span>
            </div>
        </div>

        <div class="flex items-center space-x-4 w-full">
            <Button size="icon" variant="ghost" class="h-8 w-8 rounded-full" @click.prevent="playing = !playing">
                <Loader2 class="w-4 h-4 animate-spin" v-if="loading" />
                <Pause class="h-4 w-4" v-else-if="playing" />
                <Play class="h-4 w-4" v-else />
            </Button>

            <div class="flex-1">
                <div class="text-sm">
                    <div class="font-medium">{{ liveset.title }}</div>
                    <div class="text-red-600" v-if="loading && hasPeaks === false && generatePeaksIfMissing">No peaks available. Loading full audio file to generate waveform.
                        <span class="underline cursor-pointer" @click="generatePeaksIfMissing = false; initPlayer();">Disable?</span>
                    </div>
                    <div class="text-muted-foreground" v-else>{{ liveset.artist_name }} • TFW #{{ edition.number }}</div>
                </div>
            </div>

            <div class="text-sm text-right hidden lg:block" v-if="liveset.tracks?.length">
                <div class="font-medium">Now playing</div>
                <div class="text-muted-foreground">{{ nowPlayingTrack?.title || '?' }}</div>
            </div>

            <Button variant="destructive" v-if="!loading && hasPeaks === false && !generatePeaksIfMissing" @click="generatePeaksIfMissing = true; initPlayer()"
                    title="This liveset has no waveform data. Load the full audio file to generate waveform locally?">
                Generate waveform?
            </Button>

            <div class="flex items-center space-x-1">
                <CastButton />

                <LivesetTrackList :liveset="liveset" :current-time="currentTime" button-type="ghost" v-if="liveset.tracks?.length" />

                <LivesetDescription :edition="edition" :liveset="liveset" button-type="ghost" v-if="liveset.description" />
            </div>

            <DropdownMenu>
                <DropdownMenuTrigger as="div">
                    <Button variant="outline" size="sm" class="flex items-center gap-1">
                        {{ qualities[quality] }}
                        <ChevronDown class="h-4 w-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent>
                    <DropdownMenuItem v-for="value of availableQualities" @click="quality = value" :key="value">
                        {{ qualities[value] }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </div>
</template>

<style scoped>

</style>
