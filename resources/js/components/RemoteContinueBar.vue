<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useAuth } from '@/composables/useAuth';
import { useEditions } from '@/composables/useEditions';
import { useAudioPlayer } from '@/composables/useAudioPlayer';
import { usePlaybackSync } from '@/composables/usePlaybackSync';
import { useNowPlayingState } from '@/composables/useNowPlayingState';
import { Play, X, Monitor, Smartphone, Tablet, HelpCircle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { formatDuration } from '@/lib/utils';
import type { PlaybackPosition, DeviceType, LivesetQuality } from '@/types';

const { isAuthenticated } = useAuth();
const { findLivesetById } = useEditions();
const { currentLiveset, playLiveset } = useAudioPlayer();
const { loadPositions } = usePlaybackSync();
const { restoredState } = useNowPlayingState();

// Check if local ContinuePlayingBar would be visible
const localBarVisible = computed(() => {
    if (!restoredState.value) return false;
    const found = findLivesetById(restoredState.value.liveset);
    return !!found;
});

const remotePositions = ref<PlaybackPosition[]>([]);
const dismissedIds = ref<Set<number>>(new Set());
const isLoading = ref(false);

// Device icon mapping
const deviceIcons: Record<DeviceType | 'other', typeof Monitor> = {
    desktop: Monitor,
    mobile: Smartphone,
    tablet: Tablet,
    car: Monitor, // fallback
    other: HelpCircle,
};

// Filter to remote positions (not current device), sorted by most recent
const visiblePositions = computed(() => {
    return remotePositions.value
        .filter(pos => {
            // Only show positions from other devices
            if (pos.device?.is_current) return false;
            // Don't show dismissed positions
            if (dismissedIds.value.has(pos.liveset_id)) return false;
            // Must have a liveset
            if (!pos.liveset) return false;
            return true;
        })
        .sort((a, b) => new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime())
        .slice(0, 2); // Max 2 positions
});

// Hide when something is playing
const shouldShow = computed(() => {
    return !currentLiveset.value && visiblePositions.value.length > 0;
});

function getDeviceIcon(deviceType?: DeviceType) {
    return deviceIcons[deviceType || 'other'] || HelpCircle;
}

function getDeviceName(position: PlaybackPosition): string {
    return position.device?.display_name || position.device?.device_name || 'Unknown device';
}

function playPosition(position: PlaybackPosition) {
    if (!position.liveset) return;

    // Find the full liveset with edition info
    const found = findLivesetById(position.liveset_id);
    if (!found) {
        console.warn('[RemoteContinueBar] Could not find liveset', position.liveset_id);
        return;
    }

    // Determine quality - use hq as default
    const quality: LivesetQuality = found.liveset.files?.hq ? 'hq' :
                                     found.liveset.files?.lossless ? 'lossless' : 'lq';

    playLiveset(found.edition, found.liveset, quality, position.position);
}

function dismiss(livesetId: number) {
    dismissedIds.value.add(livesetId);
}

async function fetchPositions() {
    if (!isAuthenticated.value) return;

    isLoading.value = true;
    try {
        const positions = await loadPositions();
        remotePositions.value = positions;
    } catch (error) {
        console.error('[RemoteContinueBar] Failed to fetch positions:', error);
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    fetchPositions();
});
</script>

<template>
    <TransitionGroup
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="translate-y-4 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-4 opacity-0"
        tag="div"
        class="fixed left-4 right-4 sm:left-auto sm:right-4 z-40 flex flex-col gap-2 pointer-events-none transition-[bottom] duration-300"
        :class="localBarVisible ? 'bottom-24' : 'bottom-4'"
        v-if="shouldShow"
    >
        <div
            v-for="position in visiblePositions"
            :key="position.liveset_id"
            class="flex items-center gap-3 px-3 py-2.5 bg-background/95 backdrop-blur-sm border rounded-lg shadow-lg pointer-events-auto sm:max-w-sm"
        >
            <Button
                size="sm"
                variant="ghost"
                class="h-9 w-9 rounded-full shrink-0 cursor-pointer hover:bg-primary/10"
                @click="playPosition(position)"
            >
                <Play class="h-4 w-4" />
            </Button>

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <component
                        :is="getDeviceIcon(position.device?.device_type)"
                        class="h-3 w-3 shrink-0"
                    />
                    <span class="truncate">Continue from {{ getDeviceName(position) }}</span>
                </div>
                <div class="flex items-baseline gap-1 text-sm">
                    <span class="font-medium truncate">{{ position.liveset?.title || 'Unknown' }}</span>
                    <span class="text-muted-foreground shrink-0">@ {{ formatDuration(position.position) }}</span>
                </div>
            </div>

            <Button
                size="icon"
                variant="ghost"
                class="h-7 w-7 rounded-full shrink-0 cursor-pointer hover:bg-destructive/10"
                @click="dismiss(position.liveset_id)"
            >
                <X class="h-3.5 w-3.5" />
            </Button>
        </div>
    </TransitionGroup>
</template>
