<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Play, ListMusic, User } from 'lucide-vue-next';
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

interface SharedPlaylist {
    id: number;
    name: string;
    description: string | null;
    visibility: string;
    user: { id: number; name: string };
    items: PlaylistItem[];
    created_at: string;
}

const props = defineProps<{
    shareCode: string;
}>();

const playlist = ref<SharedPlaylist | null>(null);
const loading = ref(true);
const notFound = ref(false);

async function loadPlaylist() {
    loading.value = true;
    try {
        const response = await fetch(`/api/playlists/shared/${props.shareCode}`, {
            credentials: 'include',
        });
        if (response.ok) {
            const data = await response.json();
            playlist.value = data.playlist;
        } else if (response.status === 404) {
            notFound.value = true;
        }
    } catch (error) {
        console.error('Failed to load playlist:', error);
        notFound.value = true;
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadPlaylist();
});
</script>

<template>
    <Head :title="playlist?.name || 'Shared Playlist'" />

    <div class="min-h-screen p-4">
        <div class="max-w-4xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('home')">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div v-if="playlist">
                        <h1 class="text-2xl font-bold">{{ playlist.name }}</h1>
                        <p class="text-sm text-muted-foreground flex items-center">
                            <User class="h-3 w-3 mr-1" />
                            Created by {{ playlist.user.name }}
                        </p>
                    </div>
                </div>
                <UserMenu />
            </div>

            <div v-if="loading" class="text-center py-8">
                <p class="text-muted-foreground">Loading...</p>
            </div>

            <div v-else-if="notFound" class="text-center py-8">
                <ListMusic class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                <p class="text-muted-foreground">Playlist not found.</p>
                <p class="text-sm text-muted-foreground mt-2">
                    This playlist may have been deleted or made private.
                </p>
            </div>

            <div v-else-if="playlist && playlist.items.length === 0" class="text-center py-8">
                <p class="text-muted-foreground">This playlist is empty.</p>
            </div>

            <div v-else-if="playlist" class="space-y-2">
                <p v-if="playlist.description" class="text-muted-foreground mb-4">
                    {{ playlist.description }}
                </p>

                <div
                    v-for="item in playlist.items"
                    :key="item.id"
                    class="flex items-center justify-between p-4 rounded-lg border bg-card hover:bg-muted/50 transition-colors"
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
                    <div class="text-sm text-muted-foreground">
                        {{ item.liveset?.duration_in_seconds ? formatDuration(item.liveset.duration_in_seconds) : '' }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
