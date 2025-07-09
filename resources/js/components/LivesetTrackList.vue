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

const props = defineProps<{
    liveset: Liveset;
    currentTime?: number,
    buttonType?: ButtonVariants['variant'],
}>();

const emits = defineEmits<{
    (e: 'nowPlaying', title: string|undefined): void,
    (e: 'goToTime', timestamp: number): void,
}>();

const isOpen = ref(false);

const scrollContainer = useTemplateRef<HTMLDivElement>('scrollContainer');
const trackElements = useTemplateRefsList<HTMLDivElement>();


const nowPlayingIndex = computed<number|undefined>(() => {
    if (props.currentTime === undefined || props.liveset.tracks === undefined) {
        return undefined;
    }

    let playingIndex: number|undefined = undefined;
    for (let i = 0; i < props.liveset.tracks.length; i++) {
        const track = props.liveset.tracks[i];

        if (track.timestamp === undefined) {
            continue;
        }

        if (track.timestamp <= props.currentTime) {
            playingIndex = i;
        }

        if (track.timestamp > props.currentTime) {
            break;
        }
    }

    return playingIndex;
})

const nowPlaying = computed(() => {
    if (props.liveset.tracks === undefined || nowPlayingIndex.value === undefined) {
        return undefined;
    }

    return props.liveset.tracks[nowPlayingIndex.value].title;
})


watch(nowPlaying, () => {
    emits('nowPlaying', nowPlaying.value)
});

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

onMounted(() => {
    emits('nowPlaying', nowPlaying.value);
});
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
                             @click="emits('goToTime', track.timestamp || 0)"
                             v-if="track.timestamp !== undefined">
                            {{ formatDuration(track.timestamp)}}
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
