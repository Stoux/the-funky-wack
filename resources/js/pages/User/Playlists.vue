<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { DialogTrigger } from '@/components/ui/dialog';
import { ArrowLeft, ListMusic, Plus, Lock, Globe, Link as LinkIcon, User } from 'lucide-vue-next';
import UserMenu from '@/components/UserMenu.vue';
import CreatePlaylistDialog from '@/components/CreatePlaylistDialog.vue';

interface Playlist {
    id: number;
    name: string;
    description: string | null;
    visibility: 'private' | 'public' | 'unlisted';
    share_code: string;
    slug: string;
    items_count: number;
    user?: { id: number; name: string };
    created_at: string;
    updated_at: string;
}

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);

const myPlaylists = ref<Playlist[]>([]);
const publicPlaylists = ref<Playlist[]>([]);
const loading = ref(true);
const createDialogOpen = ref(false);

async function loadPlaylists() {
    loading.value = true;
    try {
        const response = await fetch('/api/playlists', {
            credentials: 'include',
        });
        if (response.ok) {
            const data = await response.json();
            myPlaylists.value = data.playlists || [];
            publicPlaylists.value = data.publicPlaylists || [];
        }
    } catch (error) {
        console.error('Failed to load playlists:', error);
    } finally {
        loading.value = false;
    }
}

function onPlaylistCreated(playlist: Playlist) {
    myPlaylists.value.unshift(playlist);
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

function getPlaylistUrl(playlist: Playlist): string {
    return route('playlist.show', { shareCode: playlist.share_code, slug: playlist.slug });
}

onMounted(() => {
    loadPlaylists();
});
</script>

<template>
    <Head title="Playlists" />

    <div class="p-4">
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
                    <CreatePlaylistDialog
                        v-if="isAuthenticated"
                        v-model:open="createDialogOpen"
                        @created="onPlaylistCreated"
                    >
                        <DialogTrigger as-child>
                            <Button size="sm">
                                <Plus class="h-4 w-4 mr-2" />
                                New Playlist
                            </Button>
                        </DialogTrigger>
                    </CreatePlaylistDialog>
                    <UserMenu />
                </div>
            </div>

            <div v-if="loading" class="text-center py-8">
                <p class="text-muted-foreground">Loading...</p>
            </div>

            <template v-else>
                <!-- My Playlists Section -->
                <div v-if="isAuthenticated" class="space-y-4">
                    <h2 class="text-lg font-semibold text-muted-foreground">Your Playlists</h2>

                    <div v-if="myPlaylists.length === 0" class="text-center py-8 border rounded-lg">
                        <ListMusic class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                        <p class="text-muted-foreground">No playlists yet.</p>
                        <p class="text-sm text-muted-foreground mt-2">
                            Create a playlist to organize your favorite livesets!
                        </p>
                    </div>

                    <div v-else class="space-y-2">
                        <Link
                            v-for="playlist in myPlaylists"
                            :key="playlist.id"
                            :href="getPlaylistUrl(playlist)"
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

                <!-- Public Playlists Section -->
                <div v-if="publicPlaylists.length > 0" class="space-y-4">
                    <h2 class="text-lg font-semibold text-muted-foreground">
                        {{ isAuthenticated ? 'Public Playlists' : 'Playlists' }}
                    </h2>

                    <div class="space-y-2">
                        <Link
                            v-for="playlist in publicPlaylists"
                            :key="playlist.id"
                            :href="getPlaylistUrl(playlist)"
                            class="flex items-center justify-between p-4 rounded-lg border bg-card hover:bg-muted/50 transition-colors block"
                        >
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <ListMusic class="h-5 w-5 text-muted-foreground" />
                                </div>
                                <div>
                                    <p class="font-medium">{{ playlist.name }}</p>
                                    <p class="text-sm text-muted-foreground flex items-center gap-1">
                                        <User class="h-3 w-3" />
                                        {{ playlist.user?.name }}
                                        <span class="mx-1">&bull;</span>
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

                <!-- Empty state for guests -->
                <div v-if="!isAuthenticated && publicPlaylists.length === 0" class="text-center py-8">
                    <ListMusic class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                    <p class="text-muted-foreground">No public playlists yet.</p>
                    <p class="text-sm text-muted-foreground mt-2">
                        <Link :href="route('auth.login')" class="text-primary hover:underline">
                            Sign in
                        </Link>
                        to create your own playlists!
                    </p>
                </div>
            </template>
        </div>
    </div>
</template>
