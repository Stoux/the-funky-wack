<script setup lang="ts">

import {DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger} from "@/components/ui/dropdown-menu";
import {ChevronDown, Pause, Play, Loader2} from "lucide-vue-next";
import {Button} from "@/components/ui/button";
import {onBeforeUnmount, onMounted, useTemplateRef} from "vue";
import {formatDuration} from "@/lib/utils";
import LivesetTrackList from "@/components/LivesetTrackList.vue";
import LivesetDescription from "@/components/LivesetDescription.vue";
import CastButton from "@/components/CastButton.vue";
import ShareLivesetButton from "@/components/ShareLivesetButton.vue";
import {useAudioPlayer} from "@/composables/useAudioPlayer";

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
    mount,
    unmount,
    togglePlayPause,
    setQuality,
    toggleGeneratePeaks,
    initPlayer,
} = useAudioPlayer();

const waveformContainer = useTemplateRef('waveform');
const audioElement = useTemplateRef('audio');

onMounted(() => {
    if (waveformContainer.value && audioElement.value) {
        mount(waveformContainer.value, audioElement.value);
    }
});

onBeforeUnmount(() => {
    unmount();
});

</script>

<template>
    <div class="fixed bottom-0 left-0 right-0 bg-background border-t p-4 flex flex-col gap-4 z-50">

        <div class="flex-1">
            <div class="h-32 bg-muted rounded-md relative" ref="waveform">

            </div>
            <audio ref="audio" />
            <div class="flex justify-between text-xs text-muted-foreground mt-1">
                <span>{{ formatDuration(currentTime) }}</span>
                <span>{{ formatDuration(currentLiveset?.duration_in_seconds ?? 0) }}</span>
            </div>
        </div>

        <div class="flex items-center space-x-4 w-full">
            <Button size="icon" variant="ghost" class="h-8 w-8 rounded-full" @click.prevent="togglePlayPause">
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
            </div>

            <div class="text-sm text-right hidden lg:block" v-if="currentLiveset?.tracks?.length">
                <div class="font-medium">Now playing</div>
                <div class="text-muted-foreground">{{ nowPlayingTrack?.title || '?' }}</div>
            </div>

            <Button variant="destructive" v-if="!loading && hasPeaks === false && !generatePeaksIfMissing" @click="toggleGeneratePeaks"
                    title="This liveset has no waveform data. Load the full audio file to generate waveform locally?">
                Generate waveform?
            </Button>

            <div class="flex items-center space-x-1">
                <CastButton />

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
    </div>
</template>

<style scoped>

</style>
