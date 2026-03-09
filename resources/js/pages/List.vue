<script setup lang="ts">
import {Edition, Liveset, LivesetQuality} from '@/types';
import {watch, onMounted, onBeforeUnmount} from 'vue';
import {Link} from '@inertiajs/vue3';
import LivesetItem from "@/components/LivesetItem.vue";
import AutoplayButton from "@/components/AutoplayButton.vue";
import {useNowPlayingState} from "@/composables/useNowPlayingState";
import {useEditions} from "@/composables/useEditions";
import {useAudioPlayer} from "@/composables/useAudioPlayer";
import {useQueue} from "@/composables/useQueue";
import TrackSearch from "@/components/TrackSearch.vue";
import {useTrackSearch} from "@/composables/useTrackSearch";
import UserMenu from "@/components/UserMenu.vue";
import {useFavorites} from "@/composables/useFavorites";
import {Button} from "@/components/ui/button";
import {ListMusic, Radio} from "lucide-vue-next";
import {useListenAlong} from "@/composables/useListenAlong";

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

// Queue system (handles auto-advance when playing from playlists/favorites)
const { isQueueActive, clearQueue } = useQueue();

// State for the currently playing liveset
const {
    currentLiveset,
    currentEdition,
    playing,
    finished,
    autoplaying,
} = useNowPlayingState();

watch(finished, isFinished => {
    // Only autoplay from edition if queue is not active
    // Queue handles its own auto-advance
    if (isFinished && !isQueueActive.value) {
        possiblyAutoplayNextLiveset();
    }
})

// Play a liveset
const handlePlayLiveset = (edition: Edition, liveset: Liveset, quality?: LivesetQuality, atTime: number = 0) => {
    // Clear any active queue when playing from home screen
    // (user is switching to edition-based autoplay)
    clearQueue();
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

// Listen Along - fetch session count for nav indicator, with real-time updates
const { totalListeners, fetchSessions: fetchLiveSessions, joinLiveChannel, leaveLiveChannel } = useListenAlong();
onMounted(() => {
    fetchLiveSessions();
    joinLiveChannel();
});
onBeforeUnmount(() => {
    leaveLiveChannel();
});

// Pass the editions to the track search
const trackSearch = useTrackSearch();
watch(editions, (newEditions) => {
    trackSearch.withEditions(newEditions);
}, { immediate: true });

</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="space-y-3">
            <h2 class="text-4xl font-bold">
                The Funky Wack -
                <span class="text-muted-foreground">Wacky beats, the recordings.</span>
            </h2>

            <div class="flex flex-wrap items-center gap-2">
                <TrackSearch />
                <AutoplayButton v-model:autoplaying="autoplaying" />
                <Link :href="route('live')">
                    <Button variant="outline" size="sm" class="relative">
                        <Radio class="h-4 w-4 mr-2" />
                        Live
                        <span v-if="totalListeners > 0" class="ml-1 flex items-center space-x-1">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                            </span>
                            <span class="text-xs">{{ totalListeners }}</span>
                        </span>
                    </Button>
                </Link>
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
