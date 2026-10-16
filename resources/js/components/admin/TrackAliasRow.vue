<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ArrowLeftRight, Check, HelpCircle, Link2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import TrackPickerDialog from '@/components/admin/TrackPickerDialog.vue';
import type { AdminTrackAlias } from '@/types';

/**
 * A tracklist line with its linked track(s) and the review actions
 */
const props = defineProps<{
    alias: AdminTrackAlias;
    showLivesets?: boolean;
}>();

const pickerOpen = ref(false);
const statusLabels = { auto: 'To review', unknown: 'Unknown', reviewed: 'Reviewed' } as const;

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
</script>

<template>
    <div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:gap-4">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <span class="truncate font-mono text-sm">{{ alias.text }}</span>
                <Badge :variant="alias.status === 'auto' ? 'default' : 'outline'" class="shrink-0">{{ statusLabels[alias.status] }}</Badge>
            </div>
            <div class="mt-1 flex flex-wrap items-center gap-1 text-sm">
                <span class="text-muted-foreground">→</span>
                <template v-if="alias.tracks.length">
                    <span v-for="(track, index) in alias.tracks" :key="track.id">
                        <span v-if="index > 0" class="text-muted-foreground"> + </span>
                        <span class="font-medium">{{ track.name }}</span>
                    </span>
                </template>
                <span v-else class="italic text-muted-foreground">no track (unknown)</span>
            </div>
            <div v-if="showLivesets && alias.livesets.length" class="mt-1 truncate text-xs text-muted-foreground">
                In {{ alias.livesets.map(liveset => liveset.title).join(', ') }}
            </div>
        </div>

        <div class="flex shrink-0 flex-wrap gap-1">
            <Button v-if="alias.status !== 'reviewed'" variant="outline" size="sm" title="The link is right" @click="review">
                <Check class="h-3 w-3" /> Looks good
            </Button>
            <Button variant="outline" size="sm" title="The line is written as Title - Artist" @click="swap">
                <ArrowLeftRight class="h-3 w-3" /> Swap
            </Button>
            <Button variant="outline" size="sm" title="Link to other or more tracks (mashup)" @click="pickerOpen = true">
                <Link2 class="h-3 w-3" /> Link
            </Button>
            <Button v-if="alias.tracks.length || alias.status !== 'reviewed'" variant="outline" size="sm" title="Nobody knows this one" @click="markUnknown">
                <HelpCircle class="h-3 w-3" /> Unknown
            </Button>
        </div>

        <TrackPickerDialog
            v-model:open="pickerOpen"
            mode="link"
            title="Link line"
            :description="alias.text"
            :initial="alias.tracks"
            @submit="link"
        />
    </div>
</template>
