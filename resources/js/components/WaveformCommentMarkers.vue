<script setup lang="ts">
import { computed } from 'vue';
import { useComments } from '@/composables/useComments';
import { useAudioPlayer } from '@/composables/useAudioPlayer';
import { getUserColor, getUserInitials } from '@/lib/userIdentity';
import { formatDuration } from '@/lib/utils';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const props = defineProps<{
    livesetId: number;
    durationSeconds: number;
}>();

const emit = defineEmits<{
    (e: 'openComments'): void;
}>();

const { getComments } = useComments();
const { getVisiblePosition } = useAudioPlayer();

const comments = computed(() => getComments(props.livesetId));

const markers = computed(() => {
    if (!props.durationSeconds || props.durationSeconds <= 0) {
        return [];
    }

    const raw = comments.value
        .filter(c => !c.is_deleted)
        .map(c => {
            const percent = getVisiblePosition(c.timestamp_seconds);
            return {
                id: c.id,
                userId: c.user?.id ?? 0,
                name: c.user?.name ?? '?',
                timestampSeconds: c.timestamp_seconds,
                bodyPreview: (c.body ?? '').substring(0, 80),
                rawPercent: percent,
            };
        })
        .filter(c => c.rawPercent !== null && c.rawPercent! >= 0 && c.rawPercent! <= 100)
        .sort((a, b) => a.rawPercent! - b.rawPercent!);

    // Spread markers that are within 3% of each other
    const spread: typeof raw = [];
    for (const marker of raw) {
        let percent = marker.rawPercent!;
        for (const placed of spread) {
            if (Math.abs(percent - placed.rawPercent!) < 3) {
                percent = placed.rawPercent! + 3;
            }
        }
        spread.push({ ...marker, rawPercent: Math.max(1, Math.min(percent, 99)) });
    }

    return spread;
});
</script>

<template>
    <div class="absolute bottom-0 left-0 right-0 h-7 pointer-events-none z-10">
        <TooltipProvider :delay-duration="200">
            <Tooltip v-for="marker in markers" :key="marker.id">
                <TooltipTrigger as-child>
                    <button
                        class="absolute bottom-0 pointer-events-auto cursor-pointer -translate-x-1/2 rounded-full h-6 w-6 text-[9px] font-bold text-white flex items-center justify-center border border-white/50 shadow-sm hover:scale-125 transition-transform"
                        :style="{ left: marker.rawPercent + '%', backgroundColor: getUserColor(marker.userId) }"
                        @click.stop="emit('openComments')"
                    >
                        {{ getUserInitials(marker.name) }}
                    </button>
                </TooltipTrigger>
                <TooltipContent side="top" class="text-xs max-w-48">
                    <p class="font-medium">{{ marker.name }} · {{ formatDuration(marker.timestampSeconds) }}</p>
                    <p class="text-muted-foreground" v-if="marker.bodyPreview">{{ marker.bodyPreview }}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    </div>
</template>
