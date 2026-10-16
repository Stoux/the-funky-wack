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
        title: 'Tracks',
        href: route('admin.tracks'),
    },
];

const tabs: { filter: Filter; label: string }[] = [
    { filter: 'review', label: 'To review' },
    { filter: 'unknown', label: 'Unknown' },
    { filter: 'reviewed', label: 'Reviewed' },
    { filter: 'tracks', label: 'All tracks' },
    { filter: 'duplicates', label: 'Possible duplicates' },
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

// Merging: the source track, the picker gives the target
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
    <Head title="Tracks" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-2xl font-bold">Tracks</h1>
                <Input v-if="filter !== 'duplicates'" v-model="search" name="q" class="max-w-xs" placeholder="Search..." aria-label="Search tracks" />
            </div>

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

            <!-- All tracks -->
            <div v-if="tracks" class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <th class="p-4 text-left">Track</th>
                            <th class="p-4 text-left">Lines</th>
                            <th class="p-4 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="track in tracks.data" :key="track.id" class="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border">
                            <td class="p-4">{{ track.name }}</td>
                            <td class="p-4 text-sm text-muted-foreground">{{ track.aliases_count }}</td>
                            <td class="p-4">
                                <Button variant="outline" size="sm" @click="startMerge(track)">Merge into...</Button>
                            </td>
                        </tr>
                        <tr v-if="!tracks.data.length">
                            <td colspan="3" class="p-4 text-center text-muted-foreground">No tracks found</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Same title, different artist spelling -->
            <div v-if="duplicates" class="flex flex-col gap-3">
                <div v-for="(group, index) in duplicates" :key="index" class="rounded-xl border border-sidebar-border/70 px-4 divide-y dark:border-sidebar-border">
                    <div v-for="track in group" :key="track.id" class="flex items-center justify-between gap-2 py-2">
                        <span class="truncate">{{ track.name }}</span>
                        <Button variant="outline" size="sm" class="shrink-0" @click="mergeGroupInto(group, track)">Keep this one</Button>
                    </div>
                </div>
                <div v-if="!duplicates.length" class="py-4 text-center text-muted-foreground">No possible duplicates</div>
            </div>

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
            title="Merge track"
            :description="`Move every line of '${mergeSource?.name}' to another track and remove it.`"
            :exclude-id="mergeSource?.id"
            @submit="({ trackIds }) => mergeSource && trackIds[0] && merge(mergeSource, trackIds[0])"
        />
    </AppLayout>
</template>
