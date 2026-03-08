<script setup lang="ts">

import {useNowPlayingState} from "@/composables/useNowPlayingState";
import {useEditions} from "@/composables/useEditions";
import {useAudioPlayer} from "@/composables/useAudioPlayer";
import {Play, X} from "lucide-vue-next";
import {Button} from "@/components/ui/button";
import {formatDuration} from "@/lib/utils";
import {computed} from "vue";
import {Edition, Liveset, LivesetQuality} from "@/types";

const { editions } = useEditions();
const { playLiveset } = useAudioPlayer();

const {
    currentLiveset,
    restoredState,
    deleteState,
} = useNowPlayingState();

// Resolve the liveset and edition from the state to continue playing.
const resolved = computed<{
    edition: Edition,
    liveset: Liveset
}|undefined>(() => {
    const state = restoredState.value;
    if (state === undefined) {
        return undefined;
    }

    const edition = editions.value.find(edition => edition.id === state.edition);
    const liveset = edition?.livesets?.find(liveset => liveset.id === state.liveset && liveset.files && liveset.files[state.audioQuality]);

    if (!edition || !liveset) {
        return undefined;
    }

    return {
        edition,
        liveset,
    }
})

function play() {
    if (resolved.value && restoredState.value) {
        playLiveset(
            resolved.value.edition,
            resolved.value.liveset,
            restoredState.value.audioQuality as LivesetQuality,
            restoredState.value.timestamp
        );
    }
}

function close() {
    restoredState.value = undefined;
    deleteState();
}

</script>

<template>
    <div class="fixed bottom-0 left-0 right-0 bg-background border-t p-4 flex flex-col gap-4 z-50" v-if="!currentLiveset && restoredState && resolved">

        <div class="flex items-center space-x-4 w-full">

            <Button size="default" variant="outline" class="rounded-full cursor-pointer" @click="play">
                <Play class="h-4 w-4" /> Continue
            </Button>

            <div class="flex-1">
                <div class="text-sm">
                    <div class="font-medium">{{ resolved.liveset.title }}</div>
                    <div class="text-muted-foreground">{{ resolved.liveset.artist_name }} • TFW #{{ resolved.edition.number }}</div>
                </div>
            </div>

            <div class="flex justify-between text-xs text-muted-foreground mt-1">
                <span>{{ formatDuration(restoredState.timestamp) }}</span>
            </div>

            <Button size="icon" variant="outline" class="h-8 w-8 rounded-full cursor-pointer" @click="close">
                <X class="h-4 w-4" />
            </Button>

        </div>

    </div>
</template>

<style scoped>

</style>
