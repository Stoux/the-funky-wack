<script setup lang="ts">

import {FuseResult} from "fuse.js";
import {IndexedTrack} from "@/composables/useTrackSearch";
import {Button} from "@/components/ui/button";
import {Play} from "lucide-vue-next";
import {computed} from "vue";
import {useNowPlayingState} from "@/composables/useNowPlayingState";
import {formatDuration} from "@/lib/utils";

const emit = defineEmits<{
    (e: 'close'): void,
}>();

type HighlightedText = {
    text: string,
    highlight: boolean,
};

const { result } = defineProps<{
    result: FuseResult<IndexedTrack>,
}>();

const track = computed(() => result.item.track);

const titleParts = computed<HighlightedText[]>(() => {
    // Find the match(es) on the track title
    const match = result.matches?.find(match => match.key === 'track.title');
    if (!match || !match.value || !match.indices?.length || match.indices.length <= 1) {
        return [ { text: track.value.title, highlight: false } ]
    }

    // Build the parts
    const parts: HighlightedText[] = [];
    const value = match.value;
    let lastIndex = 0;

    match.indices.forEach(([start, end]) => {
        // Add any previous non-highlighted parts
        if (start > lastIndex) {
            parts.push({
                text: value.substring(lastIndex, start),
                highlight: false,
            });
        }

        // Add the highlighted part
        const text = value.substring(start, end + 1);
        parts.push({ text, highlight: text !== ' ' });
        lastIndex = end + 1;
    })

    // Add the final non-highlighted part if any text remains
    if (lastIndex < value.length) {
        parts.push({ text: value.substring(lastIndex), highlight: false });
    }

    return parts;
});

const playTitle = computed(() => {
    const timestamp = result.item.track.timestamp;
    if (!timestamp) {
        return 'Play liveset';
    }

    return `Play liveset at ` + formatDuration(timestamp);
});

const nowPlaying = useNowPlayingState();

function play() {
    nowPlaying.currentEdition.value = result.item.edition;
    nowPlaying.currentLiveset.value = result.item.liveset;
    nowPlaying.currentTime.value = result.item.track.timestamp ?? 0;
    emit('close');
}


</script>

<template>
    <div class="flex items-center gap-1 py-1 border rounded-md">
        <Button variant="ghost" size="sm" :disabled="!result.item.liveset.files"
                @click="play" :title="playTitle">
            <Play class="h-4 w-4" />
        </Button>
        <div class="flex flex-col">
            <div>
                <template v-for="(part, index) of titleParts" :key="index">
                    <mark v-if="part.highlight" class="bg-yellow-200 px-0.5 rounded-sm">
                        {{ part.text }}
                    </mark>
                    <span v-else>
                        {{ part.text }}
                    </span>
                </template>
            </div>
            <div class="text-sm text-muted-foreground">
                Track #{{ track.order }} @ {{ formatDuration(track.timestamp, undefined, '?')}}
            </div>
            <div class="text-sm text-muted-foreground">{{ result.item.liveset.title }} - TFW #{{ result.item.edition.number }}</div>
        </div>
    </div>
</template>

<style scoped>

</style>
