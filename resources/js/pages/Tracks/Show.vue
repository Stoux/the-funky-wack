<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Disc3, ExternalLink, Play } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import UserMenu from '@/components/UserMenu.vue';
import TrackLikeButton from '@/components/TrackLikeButton.vue';
import { useAudioPlayer } from '@/composables/useAudioPlayer';
import { useEditions } from '@/composables/useEditions';
import { formatDuration } from '@/lib/utils';
import type { TrackSummary } from '@/types';

interface Appearance {
    liveset_id: number;
    liveset_title: string;
    artist_name: string;
    edition_number: string;
    edition_date: string | null;
    timestamp: number | null;
    line: string;
}

const props = defineProps<{
    track: TrackSummary;
    appearances: Appearance[];
    otherVersions: TrackSummary[];
}>();

const { playLiveset } = useAudioPlayer();
const { findLivesetById } = useEditions();

const fullName = computed(() => `${props.track.artist} - ${props.track.title}${props.track.version ? ` (${props.track.version})` : ''}`);

const searchQuery = computed(() => encodeURIComponent(`${props.track.artist} ${props.track.title}${props.track.version ? ` ${props.track.version}` : ''}`));
const searchLinks = computed(() => [
    { label: 'Spotify', url: `https://open.spotify.com/search/${searchQuery.value}` },
    { label: 'YouTube', url: `https://www.youtube.com/results?search_query=${searchQuery.value}` },
    { label: 'Bandcamp', url: `https://bandcamp.com/search?q=${searchQuery.value}` },
    { label: 'Beatport', url: `https://www.beatport.com/search?q=${searchQuery.value}` },
]);

function canPlay(appearance: Appearance): boolean {
    return !!findLivesetById(appearance.liveset_id)?.liveset.files;
}

function playFrom(appearance: Appearance) {
    const result = findLivesetById(appearance.liveset_id);
    if (result) {
        playLiveset(result.edition, result.liveset, undefined, appearance.timestamp ?? 0);
    }
}
</script>

<template>
    <Head :title="fullName" />

    <div class="p-4">
        <div class="max-w-4xl mx-auto space-y-8">
            <div class="flex items-center justify-between">
                <div class="flex min-w-0 items-center space-x-4">
                    <Link :href="route('tracks.index')">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div class="min-w-0">
                        <h1 class="truncate text-2xl font-bold">
                            {{ track.title }}
                            <span v-if="track.version" class="text-muted-foreground">({{ track.version }})</span>
                        </h1>
                        <p class="truncate text-primary">{{ track.artist }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <TrackLikeButton :track="track" size="md" />
                    <UserMenu />
                </div>
            </div>

            <!-- Where it was played -->
            <section class="space-y-2">
                <h2 class="text-lg font-semibold">
                    Played in {{ appearances.length }} {{ appearances.length === 1 ? 'set' : 'sets' }}
                </h2>
                <div class="divide-y rounded-lg border">
                    <div v-for="(appearance, index) in appearances" :key="index" class="flex items-center gap-3 p-3">
                        <Button
                            size="icon"
                            variant="ghost"
                            class="h-8 w-8 shrink-0 rounded-full"
                            :disabled="!canPlay(appearance)"
                            :title="appearance.timestamp !== null ? `Play from ${formatDuration(appearance.timestamp)}` : 'Play set'"
                            @click="playFrom(appearance)"
                        >
                            <Play class="h-4 w-4" />
                        </Button>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium">{{ appearance.liveset_title }}</div>
                            <div class="truncate text-sm">
                                <span class="text-primary">{{ appearance.artist_name }}</span>
                                <span class="text-muted-foreground"> &bull; TFW #{{ appearance.edition_number }}</span>
                                <span v-if="appearance.edition_date" class="text-muted-foreground"> &bull; {{ appearance.edition_date }}</span>
                            </div>
                        </div>
                        <span v-if="appearance.timestamp !== null" class="shrink-0 font-mono text-sm tabular-nums text-muted-foreground">
                            {{ formatDuration(appearance.timestamp) }}
                        </span>
                    </div>
                    <div v-if="!appearances.length" class="p-3 text-muted-foreground">Not in any tracklist anymore.</div>
                </div>
            </section>

            <!-- Original, remixes, edits -->
            <section v-if="otherVersions.length" class="space-y-2">
                <h2 class="text-lg font-semibold">Other versions</h2>
                <div class="divide-y rounded-lg border">
                    <Link
                        v-for="version in otherVersions"
                        :key="version.slug"
                        :href="route('tracks.show', version.slug)"
                        class="flex items-center gap-3 p-3 hover:bg-muted/50 transition-colors"
                    >
                        <Disc3 class="h-5 w-5 shrink-0 text-muted-foreground" />
                        <span class="truncate">
                            {{ version.title }}
                            <span class="text-muted-foreground">({{ version.version ?? 'Original' }})</span>
                        </span>
                    </Link>
                </div>
            </section>

            <section class="space-y-2">
                <h2 class="text-lg font-semibold">Find it</h2>
                <div class="flex flex-wrap gap-2">
                    <Button v-for="link in searchLinks" :key="link.label" variant="outline" size="sm" as="a" :href="link.url" target="_blank" rel="noopener noreferrer">
                        {{ link.label }}
                        <ExternalLink class="ml-1 h-3 w-3" />
                    </Button>
                </div>
            </section>
        </div>
    </div>
</template>
