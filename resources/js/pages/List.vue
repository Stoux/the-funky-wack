<script setup lang="ts">
import {Edition, Liveset, LivesetFilesByQuality} from '@/types';
import {ref, computed, useTemplateRef} from 'vue';
import ListenBar from "@/components/ListenBar.vue";
import LivesetItem from "@/components/LivesetItem.vue";
import {Button} from "@/components/ui/button";
import {Repeat} from 'lucide-vue-next';
import AutoplayButton from "@/components/AutoplayButton.vue";

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
const currentLiveset = ref<Liveset | undefined>(undefined);
const currentEdition = ref<Edition | undefined>(undefined);
const audioQuality = ref<keyof LivesetFilesByQuality>('hq');
const isPlaying = ref(false);
const isAutoplaying = ref(false);
const listenBarElement = useTemplateRef<typeof ListenBar>('listenBarElement');


// Play a liveset
const playLiveset = (edition: Edition, liveset: Liveset, quality?: keyof LivesetFilesByQuality) => {
    if (currentEdition.value?.id === edition.id && currentLiveset.value?.id === liveset.id && ( quality === undefined || quality === audioQuality.value ) ) {
        // Play/pausing the current liveset.
        listenBarElement.value?.onPlayPause();
        return;
    }

    currentEdition.value = edition;
    currentLiveset.value = liveset;
    if (quality) {
        audioQuality.value = quality;
    }
};

const onLivesetFinishedPlaying = () => {
    // Early abort if autoplay is disabled.
    if (!isAutoplaying.value || !currentEdition.value || !currentLiveset.value) {
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


</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4" :class="{ 'pb-64': currentLiveset && currentEdition }">
        <div class="flex flex-col space-y-4 sm:flex-row sm:justify-between sm:items-center">
            <h2 class="text-4xl font-bold">
                The Funky Wack -
                <span class="text-muted-foreground">Wacky beats, the recordings.</span>
            </h2>

            <div class="flex space-x-2">
                <AutoplayButton v-model:autoplaying="isAutoplaying" />
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
                    <div v-if="!edition.livesets?.length">
                        <h3 class="font-medium">No livesets (yet).</h3>
                    </div>

                    <LivesetItem v-for="liveset in edition.livesets" :key="liveset.id"
                                 :edition="edition" :liveset="liveset"
                                 :is-current="liveset.id === currentLiveset?.id"
                                 :is-playing="liveset.id === currentLiveset?.id && isPlaying"
                                 @play="quality => playLiveset(edition, liveset, quality)"
                                 />
                </div>
            </div>
        </div>
    </div>

    <!-- Sticky player bar at the bottom -->
    <ListenBar v-if="currentLiveset && currentEdition" ref="listenBarElement"
               :edition="currentEdition" :liveset="currentLiveset" :qualities="qualities"
               v-model:quality="audioQuality" v-model:playing="isPlaying"
               @finished="onLivesetFinishedPlaying"
    />

</template>
