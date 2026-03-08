<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Clock, Smartphone, Monitor, Tablet, Car, HelpCircle } from 'lucide-vue-next';
import UserMenu from '@/components/UserMenu.vue';
import { getClientId } from '@/composables/useDeviceId';

interface PlayHistoryItem {
    id: number;
    liveset_id: number;
    liveset: {
        id: number;
        title: string;
        artist_name: string;
        edition_id: number;
    } | null;
    started_at_position: number;
    ended_at_position: number | null;
    duration_listened: number;
    effective_duration_listened: number;
    is_active: boolean;
    quality: string | null;
    device: {
        display_name: string;
        device_type: string;
        is_current: boolean;
    } | null;
    created_at: string;
    updated_at: string;
}

interface PaginatedResponse {
    data: PlayHistoryItem[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
}

const history = ref<PlayHistoryItem[]>([]);
const loading = ref(true);

const deviceIcons: Record<string, typeof Monitor> = {
    mobile: Smartphone,
    desktop: Monitor,
    tablet: Tablet,
    car: Car,
    other: HelpCircle,
};
const currentPage = ref(1);
const lastPage = ref(1);
const hasMore = ref(false);
const tick = ref(0); // Triggers reactivity for active duration updates
let tickInterval: number | null = null;

const hasActiveItems = computed(() => history.value.some(item => item.is_active));

// Start/stop ticking based on whether there are active items
watch(hasActiveItems, (hasActive) => {
    if (hasActive) {
        startTicking();
    } else {
        stopTicking();
    }
});

async function loadHistory(page: number = 1) {
    loading.value = true;
    try {
        const clientId = await getClientId();
        const response = await fetch(`/api/playback/history?page=${page}`, {
            credentials: 'include',
            headers: {
                'X-Client-ID': clientId,
            },
        });
        if (response.ok) {
            const data: PaginatedResponse = await response.json();
            if (page === 1) {
                history.value = data.data;
            } else {
                history.value.push(...data.data);
            }
            currentPage.value = data.current_page;
            lastPage.value = data.last_page;
            hasMore.value = data.next_page_url !== null;
        }
    } catch (error) {
        console.error('Failed to load history:', error);
    } finally {
        loading.value = false;
    }
}

function loadMore() {
    if (hasMore.value && !loading.value) {
        loadHistory(currentPage.value + 1);
    }
}

// Get live duration for an item (adds elapsed time since updated_at for active items)
function getLiveDuration(item: PlayHistoryItem): number {
    // Reference tick to trigger reactivity
    void tick.value;

    if (!item.is_active) {
        return item.effective_duration_listened;
    }

    const updatedAt = new Date(item.updated_at).getTime();
    const elapsed = Math.floor((Date.now() - updatedAt) / 1000);
    return item.duration_listened + elapsed;
}

function startTicking() {
    if (tickInterval) return;
    tickInterval = window.setInterval(() => {
        tick.value++;
    }, 1000);
}

function stopTicking() {
    if (tickInterval) {
        clearInterval(tickInterval);
        tickInterval = null;
    }
}

function formatDuration(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

function formatDate(dateString: string): string {
    const date = new Date(dateString);
    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

onMounted(async () => {
    await loadHistory();
    if (hasActiveItems.value) {
        startTicking();
    }
});

onUnmounted(() => {
    stopTicking();
});
</script>

<template>
    <Head title="Play History" />

    <div class="min-h-screen p-4">
        <div class="max-w-4xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('home')">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <h1 class="text-2xl font-bold">Play History</h1>
                </div>
                <UserMenu />
            </div>

            <div v-if="loading && history.length === 0" class="text-center py-8">
                <p class="text-muted-foreground">Loading...</p>
            </div>

            <div v-else-if="history.length === 0" class="text-center py-8">
                <Clock class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                <p class="text-muted-foreground">No play history yet.</p>
                <p class="text-sm text-muted-foreground mt-2">
                    Start listening to some livesets!
                </p>
            </div>

            <div v-else class="space-y-2">
                <div
                    v-for="item in history"
                    :key="item.id"
                    class="flex items-center justify-between p-4 rounded-lg border bg-card"
                >
                    <div class="flex items-center space-x-4">
                        <div>
                            <p class="font-medium">
                                {{ item.liveset?.title || 'Unknown liveset' }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ item.liveset?.artist_name || 'Unknown artist' }}
                            </p>
                        </div>
                    </div>
                    <div class="text-right text-sm text-muted-foreground">
                        <p>
                            {{ formatDuration(getLiveDuration(item)) }} listened
                            <span v-if="item.is_active" class="text-green-500">●</span>
                        </p>
                        <p>{{ formatDate(item.created_at) }}</p>
                        <p v-if="item.device" class="flex items-center justify-end gap-1 text-xs">
                            <component
                                :is="deviceIcons[item.device.device_type] || deviceIcons.other"
                                class="h-3 w-3"
                            />
                            {{ item.device.display_name }}
                            <span v-if="item.device.is_current" class="text-primary">(this device)</span>
                        </p>
                    </div>
                </div>

                <div v-if="hasMore" class="text-center pt-4">
                    <Button @click="loadMore" :disabled="loading" variant="outline">
                        {{ loading ? 'Loading...' : 'Load more' }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
