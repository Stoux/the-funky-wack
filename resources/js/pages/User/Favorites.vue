<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Heart, Play } from 'lucide-vue-next';
import UserMenu from '@/components/UserMenu.vue';
import FavoriteButton from '@/components/FavoriteButton.vue';
import { formatDuration } from '@/lib/utils';
import { useAudioPlayer } from '@/composables/useAudioPlayer';
import { useEditions } from '@/composables/useEditions';

const { playLiveset } = useAudioPlayer();
const { findLivesetById } = useEditions();

interface FavoriteItem {
    id: number;
    title: string;
    artist_name: string;
    edition: {
        id: number;
        number: string;
        tag_line: string;
    } | null;
    duration_in_seconds: number | null;
    favorited_at: string;
}

const favorites = ref<FavoriteItem[]>([]);
const loading = ref(true);

async function loadFavorites() {
    loading.value = true;
    try {
        const response = await fetch('/api/favorites', {
            credentials: 'include',
        });
        if (response.ok) {
            const data = await response.json();
            favorites.value = data.favorites || [];
        }
    } catch (error) {
        console.error('Failed to load favorites:', error);
    } finally {
        loading.value = false;
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

function handlePlay(livesetId: number) {
    const result = findLivesetById(livesetId);
    if (result) {
        playLiveset(result.edition, result.liveset);
    }
}

onMounted(() => {
    loadFavorites();
});
</script>

<template>
    <Head title="Favorites" />

    <div class="min-h-screen p-4">
        <div class="max-w-4xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('home')">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <h1 class="text-2xl font-bold">Favorites</h1>
                </div>
                <UserMenu />
            </div>

            <div v-if="loading" class="text-center py-8">
                <p class="text-muted-foreground">Loading...</p>
            </div>

            <div v-else-if="favorites.length === 0" class="text-center py-8">
                <Heart class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                <p class="text-muted-foreground">No favorites yet.</p>
                <p class="text-sm text-muted-foreground mt-2">
                    Click the heart on a liveset to add it to your favorites!
                </p>
            </div>

            <div v-else class="space-y-2">
                <div
                    v-for="item in favorites"
                    :key="item.id"
                    class="flex items-center justify-between p-4 rounded-lg border bg-card hover:bg-muted/50 transition-colors"
                >
                    <div class="flex items-center space-x-4">
                        <Button size="icon" variant="ghost" class="h-8 w-8 rounded-full" @click="handlePlay(item.id)">
                            <Play class="h-4 w-4" />
                        </Button>
                        <div>
                            <p class="font-medium">{{ item.title }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ item.artist_name }}
                                <span v-if="item.edition" class="text-xs">
                                    &bull; TFW #{{ item.edition.number }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-muted-foreground">
                            {{ item.duration_in_seconds ? formatDuration(item.duration_in_seconds) : '' }}
                        </span>
                        <FavoriteButton :liveset-id="item.id" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
