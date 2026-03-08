<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ArrowLeft, Play, Trash2, Share2, Copy, Check, Lock, Globe, Link as LinkIcon, Pencil } from 'lucide-vue-next';
import UserMenu from '@/components/UserMenu.vue';
import { formatDuration } from '@/lib/utils';

interface PlaylistItem {
    id: number;
    liveset_id: number;
    position: number;
    liveset: {
        id: number;
        title: string;
        artist_name: string;
        duration_in_seconds: number | null;
        edition: { id: number; number: string } | null;
    } | null;
}

interface Playlist {
    id: number;
    name: string;
    description: string | null;
    visibility: 'private' | 'public' | 'unlisted';
    share_code: string | null;
    items: PlaylistItem[];
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    playlistId: number;
}>();

const playlist = ref<Playlist | null>(null);
const loading = ref(true);
const editDialogOpen = ref(false);
const shareDialogOpen = ref(false);
const editName = ref('');
const editDescription = ref('');
const editVisibility = ref<'private' | 'public' | 'unlisted'>('private');
const saving = ref(false);
const copiedShareLink = ref(false);

const shareUrl = computed(() => {
    if (!playlist.value?.share_code) return '';
    return `${window.location.origin}/p/${playlist.value.share_code}`;
});

async function loadPlaylist() {
    loading.value = true;
    try {
        const response = await fetch(`/api/playlists/${props.playlistId}`, {
            credentials: 'include',
        });
        if (response.ok) {
            const data = await response.json();
            playlist.value = data.playlist;
        } else if (response.status === 403) {
            router.visit(route('user.playlists'));
        }
    } catch (error) {
        console.error('Failed to load playlist:', error);
    } finally {
        loading.value = false;
    }
}

function openEditDialog() {
    if (!playlist.value) return;
    editName.value = playlist.value.name;
    editDescription.value = playlist.value.description || '';
    editVisibility.value = playlist.value.visibility;
    editDialogOpen.value = true;
}

async function savePlaylist() {
    if (!playlist.value) return;

    saving.value = true;
    try {
        const response = await fetch(`/api/playlists/${playlist.value.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
            body: JSON.stringify({
                name: editName.value,
                description: editDescription.value || null,
                visibility: editVisibility.value,
            }),
        });

        if (response.ok) {
            const data = await response.json();
            playlist.value = {
                ...playlist.value!,
                ...data.playlist,
            };
            editDialogOpen.value = false;
        }
    } catch (error) {
        console.error('Failed to save playlist:', error);
    } finally {
        saving.value = false;
    }
}

async function removeItem(itemId: number, livesetId: number) {
    if (!playlist.value) return;

    try {
        const response = await fetch(`/api/playlists/${playlist.value.id}/items/${livesetId}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
        });

        if (response.ok) {
            playlist.value.items = playlist.value.items.filter(i => i.id !== itemId);
        }
    } catch (error) {
        console.error('Failed to remove item:', error);
    }
}

async function deletePlaylist() {
    if (!playlist.value || !confirm('Are you sure you want to delete this playlist?')) return;

    try {
        const response = await fetch(`/api/playlists/${playlist.value.id}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
        });

        if (response.ok) {
            router.visit(route('user.playlists'));
        }
    } catch (error) {
        console.error('Failed to delete playlist:', error);
    }
}

async function copyShareLink() {
    try {
        await navigator.clipboard.writeText(shareUrl.value);
        copiedShareLink.value = true;
        setTimeout(() => {
            copiedShareLink.value = false;
        }, 2000);
    } catch (error) {
        console.error('Failed to copy:', error);
    }
}

function getVisibilityIcon(visibility: string) {
    switch (visibility) {
        case 'public': return Globe;
        case 'unlisted': return LinkIcon;
        default: return Lock;
    }
}

function getCsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find(row => row.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

onMounted(() => {
    loadPlaylist();
});
</script>

<template>
    <Head :title="playlist?.name || 'Playlist'" />

    <div class="min-h-screen p-4">
        <div class="max-w-4xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('user.playlists')">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div v-if="playlist">
                        <div class="flex items-center space-x-2">
                            <h1 class="text-2xl font-bold">{{ playlist.name }}</h1>
                            <component :is="getVisibilityIcon(playlist.visibility)" class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <p v-if="playlist.description" class="text-sm text-muted-foreground">
                            {{ playlist.description }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <Button v-if="playlist" variant="outline" size="icon" @click="openEditDialog">
                        <Pencil class="h-4 w-4" />
                    </Button>
                    <Button v-if="playlist && playlist.visibility !== 'private'" variant="outline" size="icon" @click="shareDialogOpen = true">
                        <Share2 class="h-4 w-4" />
                    </Button>
                    <UserMenu />
                </div>
            </div>

            <div v-if="loading" class="text-center py-8">
                <p class="text-muted-foreground">Loading...</p>
            </div>

            <div v-else-if="playlist && playlist.items.length === 0" class="text-center py-8">
                <p class="text-muted-foreground">This playlist is empty.</p>
                <p class="text-sm text-muted-foreground mt-2">
                    Add livesets from the home page!
                </p>
            </div>

            <div v-else-if="playlist" class="space-y-2">
                <div
                    v-for="item in playlist.items"
                    :key="item.id"
                    class="flex items-center justify-between p-4 rounded-lg border bg-card"
                >
                    <div class="flex items-center space-x-4">
                        <Button size="icon" variant="ghost" class="h-8 w-8 rounded-full">
                            <Play class="h-4 w-4" />
                        </Button>
                        <div>
                            <p class="font-medium">{{ item.liveset?.title || 'Unknown' }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ item.liveset?.artist_name || 'Unknown' }}
                                <span v-if="item.liveset?.edition" class="text-xs">
                                    &bull; TFW #{{ item.liveset.edition.number }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-muted-foreground">
                            {{ item.liveset?.duration_in_seconds ? formatDuration(item.liveset.duration_in_seconds) : '' }}
                        </span>
                        <Button variant="ghost" size="icon" class="h-8 w-8" @click="removeItem(item.id, item.liveset_id)">
                            <Trash2 class="h-4 w-4 text-muted-foreground hover:text-destructive" />
                        </Button>
                    </div>
                </div>
            </div>

            <div v-if="playlist" class="pt-4 border-t">
                <Button variant="destructive" @click="deletePlaylist">
                    <Trash2 class="h-4 w-4 mr-2" />
                    Delete Playlist
                </Button>
            </div>
        </div>
    </div>

    <!-- Edit Dialog -->
    <Dialog v-model:open="editDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Edit Playlist</DialogTitle>
                <DialogDescription>
                    Update your playlist details.
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-4 py-4">
                <div class="space-y-2">
                    <Label for="edit-name">Name</Label>
                    <Input id="edit-name" v-model="editName" />
                </div>
                <div class="space-y-2">
                    <Label for="edit-description">Description</Label>
                    <Input id="edit-description" v-model="editDescription" placeholder="Optional description" />
                </div>
                <div class="space-y-2">
                    <Label for="edit-visibility">Visibility</Label>
                    <Select v-model="editVisibility">
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="private">Private - Only you</SelectItem>
                            <SelectItem value="unlisted">Unlisted - Anyone with link</SelectItem>
                            <SelectItem value="public">Public - Everyone</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="editDialogOpen = false">Cancel</Button>
                <Button @click="savePlaylist" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Save' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Share Dialog -->
    <Dialog v-model:open="shareDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Share Playlist</DialogTitle>
                <DialogDescription>
                    Share this playlist with others.
                </DialogDescription>
            </DialogHeader>
            <div class="py-4">
                <div class="flex items-center space-x-2">
                    <Input :value="shareUrl" readonly class="font-mono text-sm" />
                    <Button variant="outline" size="icon" @click="copyShareLink">
                        <Check v-if="copiedShareLink" class="h-4 w-4 text-green-500" />
                        <Copy v-else class="h-4 w-4" />
                    </Button>
                </div>
            </div>
            <DialogFooter>
                <Button @click="shareDialogOpen = false">Done</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
