<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, onBeforeUnmount, ref, type ComputedRef } from 'vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Radio, Users, Play, Headphones } from 'lucide-vue-next';
import UserMenu from '@/components/UserMenu.vue';
import ListenAlongSheet from '@/components/ListenAlongSheet.vue';
import { formatDuration } from '@/lib/utils';
import { useListenAlong, type LiveSession } from '@/composables/useListenAlong';

const {
    liveSessions,
    totalListeners,
    fetchSessions,
    joinLiveChannel,
    leaveLiveChannel,
    myRoomToken,
    isListeningAlong,
    currentRoomToken,
} = useListenAlong();

function isOwnSession(session: LiveSession): boolean {
    return !!myRoomToken.value && myRoomToken.value === session.channel_token;
}

function isCurrentlyListening(session: LiveSession): boolean {
    return !!isListeningAlong.value && currentRoomToken.value === session.channel_token;
}

function canJoinSession(session: LiveSession): boolean {
    return !isOwnSession(session) && !isListeningAlong.value;
}

const sortedSessions: ComputedRef<LiveSession[]> = computed(() => {
    return [...liveSessions.value].sort((a, b) => {
        const aOwn = isOwnSession(a) || isCurrentlyListening(a);
        const bOwn = isOwnSession(b) || isCurrentlyListening(b);
        if (aOwn && !bOwn) return -1;
        if (!aOwn && bOwn) return 1;
        return 0;
    });
});

// Tick every second to update estimated positions
const now = ref(Date.now());
let tickInterval: ReturnType<typeof setInterval> | null = null;

function estimatedPosition(session: LiveSession): number {
    const base = session.position;
    const updatedAt = session.position_updated_at ? new Date(session.position_updated_at).getTime() : 0;
    if (!updatedAt) return base;

    const elapsed = Math.max(0, (now.value - updatedAt) / 1000);
    const duration = session.liveset?.duration_in_seconds ?? Infinity;
    return Math.floor(Math.min(base + elapsed, duration));
}

onMounted(async () => {
    await fetchSessions();
    joinLiveChannel();
    tickInterval = setInterval(() => { now.value = Date.now(); }, 1000);
});

onBeforeUnmount(() => {
    leaveLiveChannel();
    if (tickInterval) clearInterval(tickInterval);
});
</script>

<template>
    <Head title="Live" />

    <div class="p-4">
        <div class="max-w-4xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('home')">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div class="flex items-center space-x-2">
                        <h1 class="text-2xl font-bold">Live</h1>
                        <div v-if="totalListeners > 0" class="flex items-center space-x-1 text-sm text-muted-foreground">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                            </span>
                            <span>{{ totalListeners }} listening</span>
                        </div>
                    </div>
                </div>
                <UserMenu />
            </div>

            <!-- Empty state -->
            <div v-if="sortedSessions.length === 0" class="text-center py-16">
                <Radio class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                <p class="text-muted-foreground">No one is listening right now.</p>
                <p class="text-sm text-muted-foreground mt-2">
                    Start playing a liveset and you'll appear here!
                </p>
            </div>

            <!-- Session list -->
            <div v-else class="space-y-3">
                <div
                    v-for="session in sortedSessions"
                    :key="session.channel_token"
                    class="flex items-center justify-between p-4 rounded-lg border bg-card hover:bg-muted/50 transition-colors"
                >
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
                                <Headphones class="h-5 w-5 text-primary" />
                            </div>
                            <span class="absolute -bottom-0.5 -right-0.5 flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                            </span>
                        </div>
                        <div>
                            <p class="font-medium">
                                {{ session.host.name }}
                                <span v-if="isOwnSession(session)" class="text-xs text-primary ml-1">(You)</span>
                            </p>
                            <p class="text-sm text-muted-foreground" v-if="session.liveset">
                                {{ session.liveset.title }} &bull; {{ session.liveset.artist_name }}
                                <span v-if="session.liveset.edition_number" class="text-xs">
                                    &bull; TFW #{{ session.liveset.edition_number }}
                                </span>
                            </p>
                            <div class="flex items-center space-x-3 text-xs text-muted-foreground mt-1">
                                <span v-if="session.liveset?.duration_in_seconds">
                                    {{ formatDuration(estimatedPosition(session)) }} / {{ formatDuration(session.liveset.duration_in_seconds) }}
                                </span>
                                <span class="flex items-center space-x-1">
                                    <Users class="h-3 w-3" />
                                    <span>{{ session.listeners_count + 1 }}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="isCurrentlyListening(session)" class="text-xs text-primary font-medium">
                        Listening
                    </div>
                    <ListenAlongSheet v-else-if="canJoinSession(session)" :session="session" />
                    <div v-else-if="isListeningAlong" class="text-xs text-muted-foreground">
                        Already listening
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
