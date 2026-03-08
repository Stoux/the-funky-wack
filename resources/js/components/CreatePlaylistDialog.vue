<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface CreatedPlaylist {
    id: number;
    name: string;
    share_code: string;
    slug: string;
    items_count: number;
}

const props = defineProps<{
    /** Liveset ID to auto-add after creating the playlist */
    autoAddLivesetId?: number;
}>();

const emit = defineEmits<{
    (e: 'created', playlist: CreatedPlaylist): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const newPlaylistName = ref('');
const creating = ref(false);

function getCsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find(row => row.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

async function createPlaylist() {
    if (!newPlaylistName.value.trim() || creating.value) return;

    creating.value = true;
    try {
        const response = await fetch('/api/playlists', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
            body: JSON.stringify({ name: newPlaylistName.value }),
        });

        if (response.ok) {
            const data = await response.json();
            const playlist = data.playlist;

            // Auto-add liveset if specified
            if (props.autoAddLivesetId) {
                await fetch(`/api/playlists/${playlist.share_code}/items`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-XSRF-TOKEN': getCsrfToken(),
                    },
                    credentials: 'include',
                    body: JSON.stringify({ liveset_id: props.autoAddLivesetId }),
                });
                playlist.items_count = 1;
            }

            emit('created', playlist);
            open.value = false;
            newPlaylistName.value = '';
        }
    } catch (error) {
        console.error('Failed to create playlist:', error);
    } finally {
        creating.value = false;
    }
}

// Reset form when dialog closes
watch(open, (isOpen) => {
    if (!isOpen) {
        newPlaylistName.value = '';
    }
});

function openDialog() {
    open.value = true;
}

defineExpose({ open: openDialog });
</script>

<template>
    <Dialog v-model:open="open">
        <slot />
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Create Playlist</DialogTitle>
                <DialogDescription>
                    Give your new playlist a name.
                </DialogDescription>
            </DialogHeader>
            <div class="py-4">
                <Label for="playlist-name">Name</Label>
                <Input
                    id="playlist-name"
                    v-model="newPlaylistName"
                    placeholder="My Playlist"
                    @keyup.enter="createPlaylist"
                />
            </div>
            <DialogFooter>
                <Button variant="outline" @click="open = false">
                    Cancel
                </Button>
                <Button @click="createPlaylist" :disabled="creating || !newPlaylistName.trim()">
                    {{ creating ? 'Creating...' : 'Create' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
