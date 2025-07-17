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
    restoredState,
} = useNowPlayingState();

const castMedia = useCastMedia();
const audioElement = useTemplateRef('audio');

let waveInstance: WaveSurfer|undefined = undefined;
const nowPlaying = ref<string|undefined>(undefined);
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

function onPlayPause() {
    if (!waveInstance) {
        return;
    }


    if (playing.value) {
        waveInstance.pause();
    } else {
        waveInstance.play();
    }
}

function goToTime(time: number) {
    waveInstance?.play(time);
}

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

    currentTime.value = 0;
    playing.value = false;
    finished.value = false;
    loading.value = true;
    loadingSource.value = source.value;

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

    const surfer = waveInstance = WaveSurfer.create({
        container: '#waveform',
        barWidth: 1,
        barHeight: 1, // the height of the wave
        barGap: 2,
        progressColor: '#57ECED',
        waveColor: '#B4B7BC',
        height: 256,
        normalize: true,
        mediaControls: false,
        hideScrollbar: false,
        autoCenter: false,
        minPxPerSec: 1,
        peaks: peaks,
        url: source.value,
        media: audioElement.value ?? undefined,
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

        // Check if we're restoring state & should skip to a certain spot
        if (restoredState.value && restoredState.value.liveset === props.liveset.id && restoredState.value.audioQuality === quality.value) {
            surfer.play(restoredState.value.timestamp);
        } else {
            surfer.play();
        }

        castMedia.withAudioElement(audioElement.value ?? undefined);
    })

    surfer.on('finish', () => {
        finished.value = true;
        playing.value = false;
    })

    surfer.on('timeupdate', (time) => {
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

defineExpose({
    onPlayPause,
});



</script>

<template>
    <div class="fixed bottom-0 left-0 right-0 bg-background border-t p-4 flex flex-col gap-4 z-50">

        <div class="flex-1">
            <div class="h-32 bg-muted rounded-md overflow-hidden" id="waveform">

            </div>
            <audio ref="audio" />
            <div class="flex justify-between text-xs text-muted-foreground mt-1">
                <span>{{ formatDuration(currentTime) }}</span>
                <span>{{ formatDuration(liveset.duration_in_seconds) }}</span>
            </div>
        </div>

        <div class="flex items-center space-x-4 w-full">
            <Button size="icon" variant="ghost" class="h-8 w-8 rounded-full" @click.prevent="onPlayPause">
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
                <div class="text-muted-foreground">{{ nowPlaying || '?' }}</div>
            </div>

            <Button variant="destructive" v-if="!loading && hasPeaks === false && !generatePeaksIfMissing" @click="generatePeaksIfMissing = true; initPlayer()"
                    title="This liveset has no waveform data. Load the full audio file to generate waveform locally?">
                Generate waveform?
            </Button>

            <div class="flex items-center space-x-1">
                <CastButton />

                <LivesetTrackList :liveset="liveset" :current-time="currentTime" button-type="ghost" v-if="liveset.tracks?.length"
                                  @go-to-time="goToTime" @now-playing="nowPlaying = $event" />

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
