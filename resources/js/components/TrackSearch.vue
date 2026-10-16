<script setup lang="ts">

import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle, SheetTrigger
} from "@/components/ui/sheet";
import {Button} from "@/components/ui/button";
import {X, Search, Play, Disc3} from "lucide-vue-next";
import {Input} from "@/components/ui/input";
import {Label} from "@/components/ui/label";
import {computed, ref, watch} from "vue";
import {Link} from "@inertiajs/vue3";
import {ScrollArea} from "@/components/ui/scroll-area";
import TrackLikeButton from "@/components/TrackLikeButton.vue";
import {useAudioPlayer} from "@/composables/useAudioPlayer";
import {useEditions} from "@/composables/useEditions";
import {formatDuration} from "@/lib/utils";
import type {TrackSummary} from "@/types";

interface SearchResults {
    livesets: { id: number; title: string; artist_name: string; genre: string | null; edition: { number: string } | null }[];
    tracks: (TrackSummary & { appearances: { liveset_id: number; liveset_title: string; edition_number: string; timestamp: number | null }[] })[];
    unlinked_lines: { line: string; liveset_id: number; liveset_title: string; edition_number: string | null; timestamp: number | null }[];
}

const { playLiveset } = useAudioPlayer();
const { findLivesetById } = useEditions();

const isOpen = ref(false);
const query = ref('');
const results = ref<SearchResults | null>(null);
const searching = ref(false);
const failed = ref(false);
let timeout: ReturnType<typeof setTimeout> | undefined;
// Only the answer to the latest query counts, slower earlier ones are dropped
let requestId = 0;

const total = computed(() => results.value
    ? results.value.livesets.length + results.value.tracks.length + results.value.unlinked_lines.length
    : 0);

async function runSearch(value: string) {
    const id = ++requestId;
    searching.value = true;
    failed.value = false;
    try {
        const response = await fetch(`/api/search?q=${encodeURIComponent(value)}`, {
            headers: { 'Accept': 'application/json' },
            credentials: 'include',
        });
        if (id !== requestId) {
            return;
        }
        if (!response.ok) {
            failed.value = true;
            results.value = null;
            return;
        }
        results.value = await response.json();
    } catch {
        if (id === requestId) {
            failed.value = true;
        }
    } finally {
        if (id === requestId) {
            searching.value = false;
        }
    }
}

watch(query, (value) => {
    clearTimeout(timeout);
    const trimmed = value.trim();
    if (trimmed.length < 2) {
        requestId++;
        results.value = null;
        searching.value = false;
        return;
    }
    timeout = setTimeout(() => runSearch(trimmed), 250);
});

function canPlay(livesetId: number): boolean {
    return !!findLivesetById(livesetId)?.liveset.files;
}

function play(livesetId: number, timestamp: number | null = null) {
    const result = findLivesetById(livesetId);
    if (result) {
        playLiveset(result.edition, result.liveset, undefined, timestamp ?? 0);
        isOpen.value = false;
    }
}

</script>

<template>
    <Sheet v-model:open="isOpen">
        <SheetTrigger as-child>
            <div class="flex items-center space-x-2 text-muted-foreground">
                <Button size="icon" variant="ghost" class="h-8 w-8 rounded-full" id="open-track-search"
                        title="Search for sets and tracks">
                    <Search class="h-4 w-4" />
                </Button>
                <Label class="sm:hidden">Search</Label>
            </div>
        </SheetTrigger>
        <SheetContent class="w-[90%] sm:max-w-md">
            <SheetHeader class="pt-4 pb-0">
                <SheetTitle>Search</SheetTitle>
                <SheetDescription>
                    <span v-if="failed" class="text-destructive">Search is not available right now.</span>
                    <span v-else-if="searching">Searching...</span>
                    <span v-else-if="results">Found {{ total }} {{ total === 1 ? 'result' : 'results' }}</span>
                    <span v-else>Sets, artists and tracks from every tracklist</span>
                </SheetDescription>
            </SheetHeader>

            <ScrollArea class="flex-1 min-h-0">
                <div v-if="results" class="flex flex-col gap-4 px-4">
                    <section v-if="results.tracks.length" class="flex flex-col gap-2">
                        <h3 class="text-sm font-medium text-muted-foreground">Tracks</h3>
                        <div v-for="track in results.tracks" :key="track.slug" class="rounded-md border p-2">
                            <div class="flex items-center gap-1">
                                <Link :href="route('tracks.show', track.slug)" class="min-w-0 flex-1 hover:underline" @click="isOpen = false">
                                    <div class="truncate font-medium">
                                        {{ track.title }}
                                        <span v-if="track.version" class="text-muted-foreground">({{ track.version }})</span>
                                    </div>
                                    <div class="truncate text-sm text-primary">{{ track.artist }}</div>
                                </Link>
                                <TrackLikeButton :track="track" />
                                <Link :href="route('tracks.show', track.slug)" class="rounded-full p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground" title="All sets with this track" @click="isOpen = false">
                                    <Disc3 class="h-4 w-4" />
                                </Link>
                            </div>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <Button v-for="(appearance, index) in track.appearances" :key="index"
                                        variant="outline" size="sm" class="h-7 text-xs"
                                        :disabled="!canPlay(appearance.liveset_id)"
                                        @click="play(appearance.liveset_id, appearance.timestamp)">
                                    <Play class="h-3 w-3" />
                                    {{ appearance.liveset_title }}
                                    <span v-if="appearance.timestamp !== null" class="font-mono text-muted-foreground">{{ formatDuration(appearance.timestamp) }}</span>
                                </Button>
                            </div>
                        </div>
                    </section>

                    <section v-if="results.livesets.length" class="flex flex-col gap-2">
                        <h3 class="text-sm font-medium text-muted-foreground">Sets</h3>
                        <div v-for="liveset in results.livesets" :key="liveset.id" class="flex items-center gap-1 rounded-md border p-2">
                            <Button variant="ghost" size="icon" class="h-8 w-8 shrink-0 rounded-full" :disabled="!canPlay(liveset.id)" title="Play set" @click="play(liveset.id)">
                                <Play class="h-4 w-4" />
                            </Button>
                            <div class="min-w-0">
                                <div class="truncate font-medium">{{ liveset.title }}</div>
                                <div class="truncate text-sm">
                                    <span class="text-primary">{{ liveset.artist_name }}</span>
                                    <span v-if="liveset.edition" class="text-muted-foreground"> &bull; TFW #{{ liveset.edition.number }}</span>
                                    <span v-if="liveset.genre" class="text-muted-foreground"> &bull; {{ liveset.genre }}</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-if="results.unlinked_lines.length" class="flex flex-col gap-2">
                        <h3 class="text-sm font-medium text-muted-foreground">Other tracklist lines</h3>
                        <div v-for="(line, index) in results.unlinked_lines" :key="index" class="flex items-center gap-1 rounded-md border p-2">
                            <Button variant="ghost" size="icon" class="h-8 w-8 shrink-0 rounded-full" :disabled="!canPlay(line.liveset_id)"
                                    :title="line.timestamp !== null ? `Play from ${formatDuration(line.timestamp)}` : 'Play set'"
                                    @click="play(line.liveset_id, line.timestamp)">
                                <Play class="h-4 w-4" />
                            </Button>
                            <div class="min-w-0">
                                <div class="truncate">{{ line.line }}</div>
                                <div class="truncate text-sm text-muted-foreground">
                                    {{ line.liveset_title }}<span v-if="line.edition_number"> &bull; TFW #{{ line.edition_number }}</span><span v-if="line.timestamp !== null"> &bull; {{ formatDuration(line.timestamp) }}</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <p v-if="!total" class="text-muted-foreground">Nothing found.</p>
                </div>
                <p v-else class="px-4 text-muted-foreground">Type at least 2 characters...</p>
            </ScrollArea>

            <SheetFooter class="pb-4 pt-0">
                <div class="flex flex-col gap-2 md:col-span-2">
                    <Label for="track-search-query" class="sr-only">Search sets and tracks</Label>
                    <div class="flex">
                        <Input id="track-search-query" name="q" placeholder="Search set, artist or track..." autofocus v-model="query" />
                        <Button title="Clear search query" variant="ghost" size="icon" :disabled="!query" @click="query = ''">
                            <X class="w-4 h-4" />
                        </Button>
                    </div>
                </div>
            </SheetFooter>

        </SheetContent>
    </Sheet>
</template>
