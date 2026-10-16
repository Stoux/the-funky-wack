<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';
import { X, Plus, ArrowUp } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { AdminTrack } from '@/types';

/**
 * Pick tracks for a tracklist line (several make a mashup, or a new one), or a single merge target
 */
const props = defineProps<{
    title: string;
    description: string;
    mode: 'link' | 'merge';
    initial?: AdminTrack[];
    excludeId?: number;
}>();

const emit = defineEmits<{
    (e: 'submit', payload: { trackIds: number[]; newTrack: { artist: string; title: string; version: string } | null }): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const search = ref('');
const results = ref<AdminTrack[]>([]);
const selected = ref<AdminTrack[]>([]);
const newTrack = ref({ artist: '', title: '', version: '' });
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

async function runSearch() {
    const response = await axios.get(route('admin.tracks.search'), { params: { q: search.value } });
    results.value = (response.data.tracks as AdminTrack[]).filter(track => track.id !== props.excludeId);
}

watch(open, isOpen => {
    if (!isOpen) {
        return;
    }
    search.value = '';
    selected.value = [...(props.initial ?? [])];
    newTrack.value = { artist: '', title: '', version: '' };
    runSearch();
});

watch(search, () => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    searchTimeout = setTimeout(runSearch, 250);
});

function pick(track: AdminTrack) {
    if (props.mode === 'merge') {
        selected.value = [track];
        return;
    }
    if (!selected.value.some(item => item.id === track.id)) {
        selected.value.push(track);
    }
}

function moveUp(index: number) {
    const [track] = selected.value.splice(index, 1);
    selected.value.splice(index - 1, 0, track);
}

const hasNewTrack = () => newTrack.value.artist.trim() !== '' && newTrack.value.title.trim() !== '';

function submit() {
    emit('submit', {
        trackIds: selected.value.map(track => track.id),
        newTrack: props.mode === 'link' && hasNewTrack() ? newTrack.value : null,
    });
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <div class="flex flex-col gap-3">
                <div v-if="selected.length" class="flex flex-col gap-1">
                    <Label>{{ mode === 'merge' ? 'Merge into' : selected.length > 1 ? 'Linked tracks (mashup, in order)' : 'Linked track' }}</Label>
                    <div v-for="(track, index) in selected" :key="track.id" class="flex items-center gap-2 rounded-md border px-2 py-1 text-sm">
                        <span class="flex-1 truncate">{{ track.name }}</span>
                        <Button v-if="mode === 'link' && index > 0" variant="ghost" size="icon" class="h-6 w-6" title="Move up" @click="moveUp(index)">
                            <ArrowUp class="h-3 w-3" />
                        </Button>
                        <Button variant="ghost" size="icon" class="h-6 w-6" title="Remove" @click="selected.splice(index, 1)">
                            <X class="h-3 w-3" />
                        </Button>
                    </div>
                </div>

                <Input v-model="search" name="track_search" placeholder="Search artist or title..." aria-label="Search tracks" />
                <div class="max-h-56 overflow-y-auto rounded-md border divide-y">
                    <button
                        v-for="track in results"
                        :key="track.id"
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-muted/50 cursor-pointer"
                        @click="pick(track)"
                    >
                        <Plus class="h-3 w-3 shrink-0 text-muted-foreground" />
                        <span class="truncate">{{ track.name }}</span>
                    </button>
                    <div v-if="!results.length" class="px-3 py-2 text-sm text-muted-foreground">No tracks found</div>
                </div>

                <div v-if="mode === 'link'" class="flex flex-col gap-1">
                    <Label>Or add a new track</Label>
                    <div class="grid grid-cols-3 gap-2">
                        <Input v-model="newTrack.artist" name="artist" placeholder="Artist" aria-label="New track artist" />
                        <Input v-model="newTrack.title" name="title" placeholder="Title" aria-label="New track title" />
                        <Input v-model="newTrack.version" name="version" placeholder="Version (optional)" aria-label="New track version" />
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button :disabled="mode === 'merge' ? !selected.length : !selected.length && !hasNewTrack()" @click="submit">
                    {{ mode === 'merge' ? 'Merge' : 'Link' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
