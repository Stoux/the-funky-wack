<script setup lang="ts">
import {Edition, Liveset, LivesetQuality} from '@/types';
import {watch, onMounted} from 'vue';
import {Link} from '@inertiajs/vue3';
import LivesetItem from "@/components/LivesetItem.vue";
import AutoplayButton from "@/components/AutoplayButton.vue";
import {useNowPlayingState} from "@/composables/useNowPlayingState";
import {useEditions} from "@/composables/useEditions";
import {useAudioPlayer} from "@/composables/useAudioPlayer";
import TrackSearch from "@/components/TrackSearch.vue";
import {useTrackSearch} from "@/composables/useTrackSearch";
import UserMenu from "@/components/UserMenu.vue";
import {useFavorites} from "@/composables/useFavorites";
import {Button} from "@/components/ui/button";
import {ListMusic} from "lucide-vue-next";

// Get editions from shared Inertia props
const { editions, sortedEditions } = useEditions();

// Initialize favorites from edition data
const { initializeFavorites } = useFavorites();
onMounted(() => {
    const allLivesets = editions.value.flatMap(edition => edition.livesets || []);
    initializeFavorites(allLivesets);
});

// Use the shared audio player
const { playLiveset } = useAudioPlayer();

// State for the currently playing liveset
const {
    currentLiveset,
    currentEdition,
    playing,
    finished,
    autoplaying,
} = useNowPlayingState();

watch(finished, isFinished => {
    if (isFinished) {
        possiblyAutoplayNextLiveset();
    }
})

// Play a liveset
const handlePlayLiveset = (edition: Edition, liveset: Liveset, quality?: LivesetQuality, atTime: number = 0) => {
    playLiveset(edition, liveset, quality, atTime);
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
watch(editions, (newEditions) => {
    trackSearch.withEditions(newEditions);
}, { immediate: true });

</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-col space-y-4 sm:flex-row sm:justify-between sm:items-center">
            <h2 class="text-4xl font-bold">
                The Funky Wack -
                <span class="text-muted-foreground">Wacky beats, the recordings.</span>
            </h2>

            <div class="flex space-y-2 md:space-y-0 space-x-2 flex-col md:flex-row items-center">
                <AutoplayButton v-model:autoplaying="autoplaying" />
                <TrackSearch />
                <Link :href="route('user.playlists')">
                    <Button variant="outline" size="sm">
                        <ListMusic class="h-4 w-4 mr-2" />
                        Playlists
                    </Button>
                </Link>
                <UserMenu />
            </div>
        </div>

        <!-- List of editions -->
        <div class="space-y-8" id="tfw">
            <div v-for="edition in sortedEditions" :key="edition.id" class="space-y-4" :id="'tfw' + edition.number">
                <!-- Edition header -->

                <div class="flex w-full space-x-2 items-end">
                    <a v-if="edition.poster_srcset_urls?.length && edition.poster_url"
                       :href="edition.poster_url" target="_blank" class="cursor-pointer h-full" title="View original poster">
                        <img
                            :srcset="edition.poster_srcset_urls?.map(p => `${p.url} ${p.width}w`).join(', ')"
                            sizes="96px"
                            :alt="`TFW #${edition.number} poster`"
                            class="rounded-lg h-full w-full max-w-24 max-h-32 object-contain"
                        />
                    </a>
                    <div class="border-b pb-2 h-full grow">
                        <h2 class="text-2xl font-bold">TFW <a :href="'#tfw' + edition.number" class="cursor-pointer">#{{ edition.number }}</a> <span
                            class="text-muted-foreground">- {{ edition.tag_line }}</span></h2>
                        <p class="text-sm text-muted-foreground">{{ edition.date }}</p>
                        <p class="text-sm text-muted-foreground" v-if="edition.notes">{{ edition.notes }}</p>
                    </div>
                </div>

                <!-- Livesets for this edition -->
                <div class="space-y-2">
                    <LivesetItem v-for="liveset in edition.livesets" :key="liveset.id"
                                 :edition="edition" :liveset="liveset"
                                 :is-current="liveset.id === currentLiveset?.id"
                                 :is-playing="liveset.id === currentLiveset?.id && playing"
                                 @play="quality => handlePlayLiveset(edition, liveset, quality)"
                                 />

                    <div v-if="!edition.livesets?.length || edition.empty_note">
                        <h3 class="font-medium text-muted-foreground">{{ edition.empty_note ?? 'No livesets (yet).' }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
