<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useComments } from '@/composables/useComments';
import { getUserColor, getUserInitials } from '@/lib/userIdentity';

const props = defineProps<{
    livesetId: number;
    currentTime: number;
}>();

const { getComments } = useComments();

const activeComment = ref<{
    userId: number;
    name: string;
    initials: string;
    body: string;
} | null>(null);
const visible = ref(false);
let hideTimeout: ReturnType<typeof setTimeout> | null = null;
let lastShownId: number | null = null;

const allComments = computed(() => {
    const comments = getComments(props.livesetId);
    const flat: Array<{ id: number; userId: number; name: string; body: string; timestamp: number }> = [];
    for (const c of comments) {
        if (!c.is_deleted && c.body) {
            flat.push({ id: c.id, userId: c.user?.id ?? 0, name: c.user?.name ?? '?', body: c.body, timestamp: c.timestamp_seconds });
        }
        if (c.replies) {
            for (const r of c.replies) {
                if (!r.is_deleted && r.body) {
                    flat.push({ id: r.id, userId: r.user?.id ?? 0, name: r.user?.name ?? '?', body: r.body, timestamp: r.timestamp_seconds });
                }
            }
        }
    }
    return flat.sort((a, b) => a.timestamp - b.timestamp);
});

watch(() => props.currentTime, (time) => {
    const match = allComments.value.find(c => Math.abs(c.timestamp - time) < 1);

    if (match && match.id !== lastShownId) {
        lastShownId = match.id;
        const truncated = match.body.length > 100 ? match.body.substring(0, 97) + '...' : match.body;
        activeComment.value = {
            userId: match.userId,
            name: match.name,
            initials: getUserInitials(match.name),
            body: truncated,
        };
        visible.value = true;

        if (hideTimeout) {
            clearTimeout(hideTimeout);
        }
        hideTimeout = setTimeout(() => {
            visible.value = false;
        }, 5000);
    }
});
</script>

<template>
    <div
        v-if="visible && activeComment"
        class="absolute bottom-1 left-1 right-1 z-30 flex items-center gap-2 px-3 py-1.5 rounded-md bg-black/70 backdrop-blur-sm pointer-events-none"
    >
        <div
            class="shrink-0 h-6 w-6 rounded-full text-[9px] font-bold text-white flex items-center justify-center"
            :style="{ backgroundColor: getUserColor(activeComment.userId) }"
        >
            {{ activeComment.initials }}
        </div>
        <span class="text-sm text-white truncate">
            <span class="font-medium">{{ activeComment.name }}</span>
            <span class="text-white/70 ml-1">{{ activeComment.body }}</span>
        </span>
    </div>
</template>
