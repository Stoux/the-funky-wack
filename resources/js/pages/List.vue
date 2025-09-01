<script setup lang="ts">
import {Edition, Liveset, LivesetFilesByQuality, LivesetQuality} from '@/types';
import {computed, useTemplateRef, watch} from 'vue';
import ListenBar from "@/components/ListenBar.vue";
import LivesetItem from "@/components/LivesetItem.vue";
import AutoplayButton from "@/components/AutoplayButton.vue";
import ContinuePlayingBar from "@/components/ContinuePlayingBar.vue";
import {useNowPlayingState} from "@/composables/useNowPlayingState";
import TrackSearch from "@/components/TrackSearch.vue";
import {useTrackSearch} from "@/composables/useTrackSearch";

const props = defineProps<{
    editions: Edition[],
    // Not actual files but labels
    qualities: LivesetFilesByQuality,
}>();

// Sort editions by number in descending order (newest first)
const sortedEditions = computed(() => {
    return [...props.editions].sort((a, b) => parseInt(b.number) - parseInt(a.number));
});

// State for the currently playing liveset
const {
    currentLiveset,
    currentEdition,
    audioQuality,
    currentTime,
    playing,
    finished,
    autoplaying,
    restoredState,
} = useNowPlayingState();

watch(finished, isFinished => {
    if (isFinished) {
        possiblyAutoplayNextLiveset();
    }
})

// Play a liveset
const playLiveset = (edition: Edition, liveset: Liveset, quality?: LivesetQuality, atTime: number = 0) => {
    if (currentEdition.value?.id === edition.id && currentLiveset.value?.id === liveset.id && ( quality === undefined || quality === audioQuality.value ) ) {
        // Play/pausing the current liveset.
        playing.value = !playing.value;
        return;
    }

    currentEdition.value = edition;
    currentLiveset.value = liveset;
    currentTime.value = atTime;
    if (quality) {
        audioQuality.value = quality;
    }
};

const possiblyAutoplayNextLiveset = () => {
    // Early abort if autoplay is disabled.
    if (!autoplaying.value || !currentEdition.value || !currentLiveset.value) {
        return;
    }

    // Find the next liveset in our current edition
    const nextLiveset = currentEdition.value.livesets?.find(liveset => (liveset.lineup_order ?? 0) > (currentLiveset.value?.lineup_order ?? 0) && liveset.files);
    if (nextLiveset) {
        playLiveset(currentEdition.value, nextLiveset);
        return;
    }

    // Otherwise look for a liveset in the next edition(s) => Find our index
    let currentIndex = sortedEditions.value.findIndex(edition => edition.id === currentEdition.value?.id);
    if (currentIndex === -1) {
        return;
    }

    // Go to the next edition (if it exists)
    currentIndex++;

    // Loop through the next editions
    for ( ; currentIndex < sortedEditions.value.length; currentIndex++) {
        // Check if that edition has a liveset with playable files
        const nextEdition = sortedEditions.value[currentIndex];
        const nextLiveset = nextEdition.livesets?.find(liveset => liveset.files);
        if (nextLiveset) {
            playLiveset(nextEdition, nextLiveset);
            return;
        }
    }
}

// Pass the editions to the track search
const trackSearch = useTrackSearch();
trackSearch.withEditions(props.editions);

</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4" :class="{ 'pb-64': currentLiveset && currentEdition }">
        <div class="flex flex-col space-y-4 sm:flex-row sm:justify-between sm:items-center">
            <h2 class="text-4xl font-bold">
                The Funky Wack -
                <span class="text-muted-foreground">Wacky beats, the recordings.</span>
            </h2>

            <div class="flex space-y-2 md:space-y-0 space-x-2 flex-col md:flex-row">
                <AutoplayButton v-model:autoplaying="autoplaying" />
                <TrackSearch />
            </div>
        </div>

        <!-- List of editions -->
        <div class="space-y-8" id="tfw">
            <div v-for="edition in sortedEditions" :key="edition.id" class="space-y-4" :id="'tfw' + edition.number">
                <!-- Edition header -->
                <div class="border-b pb-2">
                    <h2 class="text-2xl font-bold">TFW <a :href="'#tfw' + edition.number" class="cursor-pointer">#{{ edition.number }}</a> - <span
                        class="text-muted-foreground">{{ edition.tag_line }}</span></h2>
                    <p class="text-sm text-muted-foreground">{{ edition.date }}</p>
                    <p class="text-sm text-muted-foreground" v-if="edition.notes">{{ edition.notes }}</p>
                </div>

                <!-- Livesets for this edition -->
                <div class="space-y-2">
                    <LivesetItem v-for="liveset in edition.livesets" :key="liveset.id"
                                 :edition="edition" :liveset="liveset"
                                 :is-current="liveset.id === currentLiveset?.id"
                                 :is-playing="liveset.id === currentLiveset?.id && playing"
                                 @play="quality => playLiveset(edition, liveset, quality)"
                                 />

                    <div v-if="!edition.livesets?.length || edition.empty_note">
                        <h3 class="font-medium text-muted-foreground">{{ edition.empty_note ?? 'No livesets (yet).' }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sticky player bar at the bottom -->
    <ListenBar v-if="currentLiveset && currentEdition"
               :edition="currentEdition" :liveset="currentLiveset" :qualities="qualities"
    />

    <ContinuePlayingBar :editions="editions" @play="(edition, liveset, audioQuality, atTime) => playLiveset(edition, liveset, audioQuality, atTime)" />

</template>
