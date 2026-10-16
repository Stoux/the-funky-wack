<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useComments } from '@/composables/useComments';
import { useAudioPlayer } from '@/composables/useAudioPlayer';
import { getUserColor, getUserInitials } from '@/lib/userIdentity';
import { formatDuration } from '@/lib/utils';
import type { Comment } from '@/types';
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

const POP_DURATION_MS = 5000;

const { getComments, lastIncomingComment } = useComments();
const { getVisiblePosition, currentTime } = useAudioPlayer();

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
                bodyPreview: preview(c.body),
                replyCount: c.replies?.filter(r => !r.is_deleted).length ?? 0,
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

function preview(body: string | null): string {
    const text = body ?? '';
    return text.length > 100 ? text.substring(0, 97) + '...' : text;
}

/**
 * A marker pops its tooltip when the playhead passes it, or when someone posts on it live.
 * A reply pops its parent's marker, showing the reply.
 */
const popped = ref<{ markerId: number; name: string; body: string; isReply: boolean } | null>(null);
const hoveredId = ref<number | null>(null);
let popTimeout: ReturnType<typeof setTimeout> | null = null;
let lastPassedId: number | null = null;
// Popped live when it was posted, the playhead shouldn't pop it again right after
let livePoppedId: number | null = null;

function pop(markerId: number, comment: Comment) {
    popped.value = {
        markerId,
        name: comment.user?.name ?? '?',
        body: preview(comment.body),
        isReply: !!comment.parent_id,
    };
    if (popTimeout) {
        clearTimeout(popTimeout);
    }
    popTimeout = setTimeout(() => {
        popped.value = null;
    }, POP_DURATION_MS);
}

watch(currentTime, (time) => {
    const passed = comments.value.find(c => !c.is_deleted && Math.abs(c.timestamp_seconds - time) < 1);
    if (passed && passed.id !== lastPassedId && passed.id !== livePoppedId) {
        lastPassedId = passed.id;
        pop(passed.id, passed);
    }
});

watch(lastIncomingComment, (incoming) => {
    if (incoming?.livesetId !== props.livesetId || !incoming.comment.body) {
        return;
    }
    const markerId = incoming.comment.parent_id ?? incoming.comment.id;
    livePoppedId = markerId;
    pop(markerId, incoming.comment);
});

// A new set starts with a clean slate
watch(() => props.livesetId, () => {
    popped.value = null;
    lastPassedId = null;
    livePoppedId = null;
});

onBeforeUnmount(() => {
    if (popTimeout) {
        clearTimeout(popTimeout);
    }
});

function isOpen(markerId: number): boolean {
    return hoveredId.value === null ? popped.value?.markerId === markerId : hoveredId.value === markerId;
}

function onOpenChange(markerId: number, open: boolean) {
    if (open) {
        hoveredId.value = markerId;
    } else if (hoveredId.value === markerId) {
        hoveredId.value = null;
    }
}

function poppedFor(markerId: number) {
    return hoveredId.value !== markerId && popped.value?.markerId === markerId ? popped.value : null;
}
</script>

<template>
    <div class="absolute bottom-0 left-0 right-0 h-7 pointer-events-none z-10">
        <TooltipProvider :delay-duration="200">
            <Tooltip
                v-for="marker in markers"
                :key="marker.id"
                :open="isOpen(marker.id)"
                @update:open="onOpenChange(marker.id, $event)"
            >
                <TooltipTrigger as-child>
                    <button
                        class="absolute bottom-0 pointer-events-auto cursor-pointer -translate-x-1/2 rounded-full h-6 w-6 text-[9px] font-bold text-white flex items-center justify-center border border-white/50 shadow-sm hover:scale-125 transition-transform"
                        :class="{ 'scale-125': popped?.markerId === marker.id }"
                        :style="{ left: marker.rawPercent + '%', backgroundColor: getUserColor(marker.userId) }"
                        @click.stop="emit('openComments')"
                    >
                        {{ getUserInitials(marker.name) }}
                    </button>
                </TooltipTrigger>
                <TooltipContent side="top" class="text-xs max-w-56">
                    <template v-if="poppedFor(marker.id)?.isReply">
                        <p class="font-medium">{{ marker.name }} · {{ formatDuration(marker.timestampSeconds) }}</p>
                        <p class="text-muted-foreground truncate" v-if="marker.bodyPreview">{{ marker.bodyPreview }}</p>
                        <p class="mt-1 pl-2 border-l">
                            <span class="font-medium">{{ poppedFor(marker.id)!.name }}</span>
                            <span class="text-muted-foreground ml-1">{{ poppedFor(marker.id)!.body }}</span>
                        </p>
                    </template>
                    <template v-else>
                        <p class="font-medium">{{ marker.name }} · {{ formatDuration(marker.timestampSeconds) }}</p>
                        <p class="text-muted-foreground" v-if="marker.bodyPreview">{{ marker.bodyPreview }}</p>
                        <p class="text-muted-foreground mt-1" v-if="marker.replyCount">
                            {{ marker.replyCount }} {{ marker.replyCount === 1 ? 'reply' : 'replies' }}
                        </p>
                    </template>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    </div>
</template>
