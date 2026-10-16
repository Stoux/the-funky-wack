<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { Heart } from 'lucide-vue-next';
import { useAuth } from '@/composables/useAuth';
import { useTrackLikes } from '@/composables/useTrackLikes';
import type { TrackSummary } from '@/types';

/**
 * Heart for a track, only for logged in users
 */
const props = defineProps<{
    track: TrackSummary;
    size?: 'sm' | 'md';
}>();

const { isAuthenticated } = useAuth();
const { ensureLoaded, isLiked, toggleLike } = useTrackLikes();

const liked = computed(() => isLiked(props.track.slug));
const label = computed(() => `${liked.value ? 'Unlike' : 'Like'} ${props.track.artist} - ${props.track.title}`);

onMounted(() => ensureLoaded());
</script>

<template>
    <button
        v-if="isAuthenticated"
        type="button"
        class="shrink-0 rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground cursor-pointer"
        :class="[size === 'md' ? 'p-2' : 'p-1.5', { 'text-red-500 hover:text-red-500': liked }]"
        :title="label"
        :aria-label="label"
        :aria-pressed="liked"
        @click.stop="toggleLike(track.slug)"
    >
        <Heart :class="[size === 'md' ? 'h-5 w-5' : 'h-4 w-4', { 'fill-current': liked }]" />
    </button>
</template>
