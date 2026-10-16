<script setup lang="ts">
import {Edition, Liveset, LivesetQuality} from '@/types';
import {onMounted, onBeforeUnmount} from 'vue';
import {Link} from '@inertiajs/vue3';
import LivesetItem from "@/components/LivesetItem.vue";
import AutoplayButton from "@/components/AutoplayButton.vue";
import {useNowPlayingState} from "@/composables/useNowPlayingState";
import {useEditions} from "@/composables/useEditions";
import {useAudioPlayer} from "@/composables/useAudioPlayer";
import {useQueue} from "@/composables/useQueue";
import TrackSearch from "@/components/TrackSearch.vue";
import UserMenu from "@/components/UserMenu.vue";
import {useFavorites} from "@/composables/useFavorites";
import {useComments} from "@/composables/useComments";
import {Button} from "@/components/ui/button";
import {Disc3, ListMusic, Radio} from "lucide-vue-next";
import {useListenAlong} from "@/composables/useListenAlong";

// Get editions from shared Inertia props
const { editions, sortedEditions } = useEditions();

// Initialize favorites + comment counts from edition data
const { initializeFavorites } = useFavorites();
const { initializeCommentCounts } = useComments();
onMounted(() => {
    const allLivesets = editions.value.flatMap(edition => edition.livesets || []);
    initializeFavorites(allLivesets);
    initializeCommentCounts(allLivesets);
});

// Use the shared audio player
const { playLiveset } = useAudioPlayer();

// Queue system (handles auto-advance for both the queue and edition autoplay)
const { clearQueue } = useQueue();

// State for the currently playing liveset
const {
    currentLiveset,
    playing,
    autoplaying,
} = useNowPlayingState();

// Play a liveset
const handlePlayLiveset = (edition: Edition, liveset: Liveset, quality?: LivesetQuality, atTime: number = 0) => {
    // Clear any active queue when playing from home screen
    // (user is switching to edition-based autoplay)
    clearQueue();
    playLiveset(edition, liveset, quality, atTime);
};

// Listen Along - fetch session count for nav indicator, with real-time updates
const { totalListeners, fetchSessions: fetchLiveSessions, joinLiveChannel, leaveLiveChannel } = useListenAlong();
onMounted(() => {
    fetchLiveSessions();
    joinLiveChannel();
});
onBeforeUnmount(() => {
    leaveLiveChannel();
});

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
                <Link :href="route('tracks.index')">
                    <Button variant="outline" size="sm">
                        <Disc3 class="h-4 w-4 mr-2" />
                        Tracks
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
