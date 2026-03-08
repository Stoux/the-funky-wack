<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import { ArrowLeft, ListMusic, Plus, Lock, Globe, Link as LinkIcon } from 'lucide-vue-next';
import UserMenu from '@/components/UserMenu.vue';

interface Playlist {
    id: number;
    name: string;
    description: string | null;
    visibility: 'private' | 'public' | 'unlisted';
    share_code: string | null;
    items_count: number;
    created_at: string;
    updated_at: string;
}

const playlists = ref<Playlist[]>([]);
const loading = ref(true);
const createDialogOpen = ref(false);
const newPlaylistName = ref('');
const creating = ref(false);

async function loadPlaylists() {
    loading.value = true;
    try {
        const response = await fetch('/api/playlists', {
            credentials: 'include',
        });
        if (response.ok) {
            const data = await response.json();
            playlists.value = data.playlists || [];
        }
    } catch (error) {
        console.error('Failed to load playlists:', error);
    } finally {
        loading.value = false;
    }
}

async function createPlaylist() {
    if (!newPlaylistName.value.trim()) return;

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
            playlists.value.unshift(data.playlist);
            createDialogOpen.value = false;
            newPlaylistName.value = '';
        }
    } catch (error) {
        console.error('Failed to create playlist:', error);
    } finally {
        creating.value = false;
    }
}

function getVisibilityIcon(visibility: string) {
    switch (visibility) {
        case 'public': return Globe;
        case 'unlisted': return LinkIcon;
        default: return Lock;
    }
}

function formatDate(dateString: string): string {
    const date = new Date(dateString);
    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function getCsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find(row => row.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

onMounted(() => {
    loadPlaylists();
});
</script>

<template>
    <Head title="Playlists" />

    <div class="min-h-screen p-4">
        <div class="max-w-4xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('home')">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <h1 class="text-2xl font-bold">Playlists</h1>
                </div>
                <div class="flex items-center space-x-2">
                    <Dialog v-model:open="createDialogOpen">
                        <DialogTrigger as-child>
                            <Button size="sm">
                                <Plus class="h-4 w-4 mr-2" />
                                New Playlist
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Create Playlist</DialogTitle>
                                <DialogDescription>
                                    Give your new playlist a name.
                                </DialogDescription>
                            </DialogHeader>
                            <div class="py-4">
                                <Label for="name">Name</Label>
                                <Input
                                    id="name"
                                    v-model="newPlaylistName"
                                    placeholder="My Playlist"
                                    @keyup.enter="createPlaylist"
                                />
                            </div>
                            <DialogFooter>
                                <Button variant="outline" @click="createDialogOpen = false">
                                    Cancel
                                </Button>
                                <Button @click="createPlaylist" :disabled="creating || !newPlaylistName.trim()">
                                    {{ creating ? 'Creating...' : 'Create' }}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                    <UserMenu />
                </div>
            </div>

            <div v-if="loading" class="text-center py-8">
                <p class="text-muted-foreground">Loading...</p>
            </div>

            <div v-else-if="playlists.length === 0" class="text-center py-8">
                <ListMusic class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                <p class="text-muted-foreground">No playlists yet.</p>
                <p class="text-sm text-muted-foreground mt-2">
                    Create a playlist to organize your favorite livesets!
                </p>
            </div>

            <div v-else class="space-y-2">
                <Link
                    v-for="playlist in playlists"
                    :key="playlist.id"
                    :href="route('user.playlist', { playlist: playlist.id })"
                    class="flex items-center justify-between p-4 rounded-lg border bg-card hover:bg-muted/50 transition-colors block"
                >
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            <ListMusic class="h-5 w-5 text-muted-foreground" />
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <p class="font-medium">{{ playlist.name }}</p>
                                <component :is="getVisibilityIcon(playlist.visibility)" class="h-3 w-3 text-muted-foreground" />
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ playlist.items_count }} {{ playlist.items_count === 1 ? 'liveset' : 'livesets' }}
                            </p>
                        </div>
                    </div>
                    <div class="text-right text-sm text-muted-foreground">
                        <p>{{ formatDate(playlist.updated_at) }}</p>
                    </div>
                </Link>
            </div>
        </div>
    </div>
</template>
