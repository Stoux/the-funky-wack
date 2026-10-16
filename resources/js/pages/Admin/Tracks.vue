<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type AdminTrack, type AdminTrackAlias, type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import TrackAliasRow from '@/components/admin/TrackAliasRow.vue';
import TrackPickerDialog from '@/components/admin/TrackPickerDialog.vue';

type Filter = 'review' | 'unknown' | 'reviewed' | 'tracks' | 'duplicates';

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    filter: Filter;
    search: string;
    counts: Record<Filter, number>;
    aliases: Paginated<AdminTrackAlias> | null;
    tracks: Paginated<AdminTrack> | null;
    duplicates: AdminTrack[][] | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Songs',
        href: route('admin.tracks'),
    },
];

const tabs: { filter: Filter; label: string }[] = [
    { filter: 'review', label: 'Lines to review' },
    { filter: 'unknown', label: 'Unknown lines' },
    { filter: 'reviewed', label: 'Reviewed lines' },
    { filter: 'tracks', label: 'All songs' },
    { filter: 'duplicates', label: 'Possible duplicate songs' },
];

const search = ref(props.search);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;
watch(search, value => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    searchTimeout = setTimeout(() => {
        router.get(route('admin.tracks'), { filter: props.filter, q: value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

// Merging: the source song, the picker gives the one to keep
const mergeSource = ref<AdminTrack | null>(null);
const mergeOpen = ref(false);

function startMerge(track: AdminTrack) {
    mergeSource.value = track;
    mergeOpen.value = true;
}

function merge(source: AdminTrack, targetId: number, onSuccess?: () => void) {
    router.post(route('admin.tracks.merge', source.id), { target_id: targetId }, { preserveScroll: true, onSuccess });
}

// One after the other, a new Inertia visit cancels the running one
function mergeGroupInto(group: AdminTrack[], target: AdminTrack) {
    const [next, ...rest] = group.filter(item => item.id !== target.id);
    if (next) {
        merge(next, target.id, () => mergeGroupInto(rest, target));
    }
}

const paginated = computed(() => props.aliases ?? props.tracks);
</script>

<template>
    <Head title="Songs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-2xl font-bold">Songs</h1>
                <Input v-if="filter !== 'duplicates'" v-model="search" name="q" class="w-full sm:max-w-xs" placeholder="Search..." aria-label="Search" />
            </div>

            <p class="max-w-3xl text-sm text-muted-foreground">
                A <strong class="text-foreground">line</strong> is the text in a set's tracklist. A <strong class="text-foreground">song</strong> is the song itself,
                and several lines (in different sets, or spelled differently) can point to the same song. New lines are linked automatically:
                to an existing song with the same name, or to a new song made from the line. Check those guesses here.
                A fix applies to every set with that exact line.
            </p>

            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="tab in tabs"
                    :key="tab.filter"
                    :variant="filter === tab.filter ? 'default' : 'outline'"
                    size="sm"
                    :as="Link"
                    :href="route('admin.tracks', { filter: tab.filter })"
                    preserve-scroll
                >
                    {{ tab.label }} <span class="ml-1 opacity-70">{{ counts[tab.filter] }}</span>
                </Button>
            </div>

            <!-- Tracklist lines -->
            <div v-if="aliases" class="rounded-xl border border-sidebar-border/70 px-4 divide-y dark:border-sidebar-border">
                <TrackAliasRow v-for="alias in aliases.data" :key="alias.id" :alias="alias" show-livesets />
                <div v-if="!aliases.data.length" class="py-4 text-center text-muted-foreground">Nothing here</div>
            </div>

            <!-- All songs, with the lines that point to them -->
            <div v-if="tracks" class="rounded-xl border border-sidebar-border/70 px-4 divide-y dark:border-sidebar-border">
                <div v-for="track in tracks.data" :key="track.id" class="flex flex-col gap-2 py-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-baseline gap-x-4 text-sm">
                            <span><span class="text-xs text-muted-foreground">artist </span><span class="font-medium">{{ track.artist }}</span></span>
                            <span><span class="text-xs text-muted-foreground">title </span><span class="font-medium">{{ track.title }}</span></span>
                            <span v-if="track.version"><span class="text-xs text-muted-foreground">version </span><span class="font-medium">{{ track.version }}</span></span>
                        </div>
                        <ul class="mt-1 flex flex-col gap-0.5 text-xs text-muted-foreground">
                            <li v-for="line in track.lines" :key="line.id" class="truncate">
                                <span class="font-mono">{{ line.text }}</span>
                                <span v-if="line.livesets.length"> · {{ line.livesets.join(', ') }}</span>
                            </li>
                        </ul>
                    </div>
                    <Button variant="outline" size="sm" class="shrink-0" title="Same song as another one: move its lines over" @click="startMerge(track)">
                        Merge into another song...
                    </Button>
                </div>
                <div v-if="!tracks.data.length" class="py-4 text-center text-muted-foreground">No songs found</div>
            </div>

            <!-- Same title, different artist spelling -->
            <template v-if="duplicates">
                <p class="text-sm text-muted-foreground">Songs with the same title but a different artist. If they're the same song, keep one: the lines of the others move to it.</p>
                <div v-for="(group, index) in duplicates" :key="index" class="rounded-xl border border-sidebar-border/70 px-4 divide-y dark:border-sidebar-border">
                    <div v-for="track in group" :key="track.id" class="flex items-center justify-between gap-2 py-2">
                        <span class="truncate text-sm">
                            {{ track.name }}
                            <span class="text-xs text-muted-foreground"> · {{ track.lines_count }} line{{ track.lines_count === 1 ? '' : 's' }}</span>
                        </span>
                        <Button variant="outline" size="sm" class="shrink-0" @click="mergeGroupInto(group, track)">Keep this one</Button>
                    </div>
                </div>
                <div v-if="!duplicates.length" class="py-4 text-center text-muted-foreground">No possible duplicates</div>
            </template>

            <div v-if="paginated && paginated.last_page > 1" class="flex items-center justify-between">
                <Button variant="outline" size="sm" :disabled="!paginated.prev_page_url" :as="paginated.prev_page_url ? Link : 'button'" :href="paginated.prev_page_url ?? undefined" preserve-scroll>
                    Previous
                </Button>
                <span class="text-sm text-muted-foreground">Page {{ paginated.current_page }} of {{ paginated.last_page }}</span>
                <Button variant="outline" size="sm" :disabled="!paginated.next_page_url" :as="paginated.next_page_url ? Link : 'button'" :href="paginated.next_page_url ?? undefined" preserve-scroll>
                    Next
                </Button>
            </div>
        </div>

        <TrackPickerDialog
            v-model:open="mergeOpen"
            mode="merge"
            title="Merge into another song"
            :description="`Every line of '${mergeSource?.name}' moves to the song you pick, then '${mergeSource?.name}' is removed.`"
            :exclude-id="mergeSource?.id"
            @submit="({ trackIds }) => mergeSource && trackIds[0] && merge(mergeSource, trackIds[0])"
        />
    </AppLayout>
</template>
