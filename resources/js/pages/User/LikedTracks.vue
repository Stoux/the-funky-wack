<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Heart, Play } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import UserMenu from '@/components/UserMenu.vue';
import TrackLikeButton from '@/components/TrackLikeButton.vue';
import { useTrackLikes, type LikedTrack } from '@/composables/useTrackLikes';
import { useAudioPlayer } from '@/composables/useAudioPlayer';
import { useEditions } from '@/composables/useEditions';
import { formatDuration } from '@/lib/utils';

const { likedTracks, reload } = useTrackLikes();
const { playLiveset } = useAudioPlayer();
const { findLivesetById } = useEditions();

const loading = ref(true);
onMounted(async () => {
    await reload();
    loading.value = false;
});

function playFrom(appearance: LikedTrack['appearances'][number]) {
    const result = findLivesetById(appearance.liveset_id);
    if (result) {
        playLiveset(result.edition, result.liveset, undefined, appearance.timestamp ?? 0);
    }
}
</script>

<template>
    <Head title="Liked tracks" />

    <div class="p-4">
        <div class="max-w-4xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('home')">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <h1 class="text-2xl font-bold">Liked tracks</h1>
                </div>
                <UserMenu />
            </div>

            <div v-if="loading" class="text-center py-8 text-muted-foreground">Loading...</div>

            <div v-else-if="!likedTracks.length" class="text-center py-8 border rounded-lg">
                <Heart class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                <p class="text-muted-foreground">No liked tracks yet.</p>
                <p class="text-sm text-muted-foreground mt-2">Tap the heart next to a track in a tracklist or in the player.</p>
            </div>

            <div v-else class="divide-y rounded-lg border">
                <div v-for="track in likedTracks" :key="track.slug" class="p-3">
                    <div class="flex items-center gap-2">
                        <Link :href="route('tracks.show', track.slug)" class="min-w-0 flex-1 hover:underline">
                            <div class="truncate font-medium">
                                {{ track.title }}
                                <span v-if="track.version" class="text-muted-foreground">({{ track.version }})</span>
                            </div>
                            <div class="truncate text-sm text-primary">{{ track.artist }}</div>
                        </Link>
                        <TrackLikeButton :track="track" />
                    </div>
                    <div class="mt-1 flex flex-wrap gap-1">
                        <Button
                            v-for="(appearance, index) in track.appearances"
                            :key="index"
                            variant="outline"
                            size="sm"
                            class="h-7 text-xs"
                            :disabled="!findLivesetById(appearance.liveset_id)?.liveset.files"
                            @click="playFrom(appearance)"
                        >
                            <Play class="h-3 w-3" />
                            {{ appearance.liveset_title }}
                            <span v-if="appearance.timestamp !== null" class="font-mono text-muted-foreground">{{ formatDuration(appearance.timestamp) }}</span>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
