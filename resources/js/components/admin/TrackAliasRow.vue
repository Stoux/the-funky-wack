<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ArrowLeftRight, Check, HelpCircle, Search } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import TrackPickerDialog from '@/components/admin/TrackPickerDialog.vue';
import type { AdminTrack, AdminTrackAlias } from '@/types';

/**
 * A tracklist line, the song(s) it stands for, and the review actions
 */
const props = defineProps<{
    alias: AdminTrackAlias;
    showLivesets?: boolean;
}>();

const pickerOpen = ref(false);
const statusLabels = { auto: 'Guessed, to review', unknown: 'Guessed unknown', reviewed: 'Reviewed' } as const;

const options = { preserveScroll: true, preserveState: true };

function review() {
    router.post(route('admin.track-aliases.review', props.alias.id), {}, options);
}

function swap() {
    router.post(route('admin.track-aliases.swap', props.alias.id), {}, options);
}

function markUnknown() {
    router.post(route('admin.track-aliases.unknown', props.alias.id), {}, options);
}

function link(payload: { trackIds: number[]; newTrack: { artist: string; title: string; version: string } | null }) {
    router.put(route('admin.track-aliases.link', props.alias.id), {
        track_ids: payload.trackIds,
        new_track: payload.newTrack,
    }, options);
}

function usage(track: AdminTrack): string {
    const others = (track.lines_count ?? 1) - 1;
    return others > 0 ? `existing song, also used by ${others} other line${others === 1 ? '' : 's'}` : 'only used by this line';
}
</script>

<template>
    <div class="flex flex-col gap-3 py-4">
        <!-- What's in the tracklist -->
        <div class="grid grid-cols-[6rem_1fr] items-baseline gap-x-3 gap-y-1">
            <span class="text-xs uppercase tracking-wide text-muted-foreground">Line</span>
            <div class="flex min-w-0 flex-wrap items-center gap-2">
                <span class="font-mono text-sm break-all">{{ alias.text }}</span>
                <Badge :variant="alias.status === 'reviewed' ? 'outline' : 'default'" class="shrink-0">{{ statusLabels[alias.status] }}</Badge>
            </div>
            <template v-if="showLivesets && alias.livesets.length">
                <span class="text-xs uppercase tracking-wide text-muted-foreground">In set</span>
                <span class="truncate text-sm text-muted-foreground">{{ alias.livesets.map(liveset => liveset.title).join(', ') }}</span>
            </template>

            <!-- What we think it is -->
            <span class="text-xs uppercase tracking-wide text-muted-foreground">{{ alias.tracks.length > 1 ? 'Songs (mashup)' : 'Song' }}</span>
            <div v-if="alias.tracks.length" class="flex flex-col gap-1">
                <div v-for="track in alias.tracks" :key="track.id" class="flex flex-wrap items-baseline gap-x-4 gap-y-0.5 text-sm">
                    <span><span class="text-xs text-muted-foreground">artist </span><span class="font-medium">{{ track.artist }}</span></span>
                    <span><span class="text-xs text-muted-foreground">title </span><span class="font-medium">{{ track.title }}</span></span>
                    <span v-if="track.version"><span class="text-xs text-muted-foreground">version </span><span class="font-medium">{{ track.version }}</span></span>
                    <span class="text-xs text-muted-foreground">· {{ usage(track) }}</span>
                </div>
            </div>
            <span v-else class="text-sm italic text-muted-foreground">none, not a known song</span>
        </div>

        <div class="flex flex-wrap gap-1 sm:pl-[6.75rem]">
            <Button v-if="alias.status !== 'reviewed'" variant="outline" size="sm" @click="review">
                <Check class="h-3 w-3" /> Correct
            </Button>
            <Button v-if="alias.tracks.length <= 1" variant="outline" size="sm" title="The line is written as Title - Artist" @click="swap">
                <ArrowLeftRight class="h-3 w-3" /> Artist and title swapped
            </Button>
            <Button variant="outline" size="sm" title="Link to an existing or new song, or several for a mashup" @click="pickerOpen = true">
                <Search class="h-3 w-3" /> Pick another song...
            </Button>
            <Button v-if="alias.tracks.length || alias.status !== 'reviewed'" variant="outline" size="sm" @click="markUnknown">
                <HelpCircle class="h-3 w-3" /> Not a known song
            </Button>
        </div>

        <TrackPickerDialog
            v-model:open="pickerOpen"
            mode="link"
            title="Pick the song for this line"
            :description="alias.text"
            :initial="alias.tracks"
            @submit="link"
        />
    </div>
</template>
