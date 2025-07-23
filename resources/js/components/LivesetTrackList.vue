<script setup lang="ts">

import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription, SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger
} from "@/components/ui/sheet";
import {Button, type ButtonVariants} from "@/components/ui/button";
import {ListMusic} from "lucide-vue-next";
import {Liveset} from "@/types";
import {computed, nextTick, onMounted, ref, useTemplateRef, watch} from "vue";
import {formatDuration} from "@/lib/utils";
import {ScrollArea} from "@/components/ui/scroll-area";
import {useTemplateRefsList} from "@vueuse/core";
import {useTracklistNowPlaying} from "@/composables/useTracklistNowPlaying";
import {useNowPlayingState} from "@/composables/useNowPlayingState";

const props = defineProps<{
    liveset: Liveset;
    buttonType?: ButtonVariants['variant'],
}>();

const isOpen = ref(false);

const scrollContainer = useTemplateRef<HTMLDivElement>('scrollContainer');
const trackElements = useTemplateRefsList<HTMLDivElement>();

const {
    currentLiveset,
    currentTime,
} = useNowPlayingState();

const {
    nowPlayingTrack,
} = useTracklistNowPlaying();


const isCurrentLiveset = computed(() => {
    return currentLiveset.value?.id === props.liveset.id;
})

/**
 * The index of the currently playing track in the original tracklist.
 */
const nowPlayingIndex = computed<number|undefined>(() => {
    if (!isCurrentLiveset.value) {
        return undefined;
    }

    return nowPlayingTrack.value?.originalTrackIndex;
})

watch(isOpen, open => {
    if (!open || nowPlayingIndex.value === undefined) {
        return;
    }

    // Scroll to the track currently playing by finding js-now-playing inside the list
    nextTick(() => {
        if (!trackElements.value?.length || nowPlayingIndex.value === undefined) {
            return;
        }

        const nowPlayingDiv = trackElements.value[nowPlayingIndex.value];
        if (nowPlayingDiv) {
            nowPlayingDiv.scrollIntoView({
                behavior: 'instant',
            })
        }
    });
});

function goToTime(timestamp: number) {
    if (!isCurrentLiveset.value) {
        return;
    }

    // This will also start playing the track if not already playing.
    currentTime.value = timestamp;
}

</script>

<template>
    <Sheet v-model:open="isOpen">
        <SheetTrigger as-child>
            <Button size="icon" :variant="buttonType ?? 'outline'" class="h-8 w-8 rounded-full cursor-pointer"
                    title="View tracklist"
                    :disabled="!liveset.tracks?.length">
                <ListMusic class="h-4 w-4" />
            </Button>
        </SheetTrigger>
        <SheetContent>
            <SheetHeader>
                <SheetTitle>Tracklist</SheetTitle>
                <SheetDescription>
                    List of tracks in the liveset {{ liveset.title }}
                </SheetDescription>
            </SheetHeader>

            <ScrollArea class="h-full" ref="scrollContainer">
                <div class="flex flex-col" v-if="liveset.tracks?.length" ref="listDiv">
                    <div class="px-4 py-2" v-for="(track, index) of liveset.tracks" :key="track.id" ref="trackElements">
                        <div :class="{ 'text-green-600 js-now-playing': index === nowPlayingIndex, 'text-muted-foreground': index !== nowPlayingIndex, 'cursor-pointer': index !== undefined }"
                             @click="goToTime(track.timestamp ?? 0)"
                             v-if="track.timestamp !== null">
                            #{{ track.order }} &bull; {{ formatDuration(track.timestamp)}}
                        </div>
                        <div v-else class="text-muted-foreground">
                            #{{ track.order }}
                        </div>
                        <div class="font-medium">
                            {{ track.title }}
                        </div>
                    </div>
                </div>

                <div class="flex flex-col" v-if="!liveset.tracks?.length">
                    <div class="px-4 py-2 text-muted-foreground">
                        No tracks found!
                    </div>
                </div>
            </ScrollArea>

            <SheetFooter>
                <SheetClose as-child>
                    <Button>
                        Close
                    </Button>
                </SheetClose>
            </SheetFooter>

        </SheetContent>
    </Sheet>
</template>

<style scoped>

</style>
