<script setup lang="ts">
import { computed } from 'vue';
import { getUserColor, getUserInitials } from '@/lib/userIdentity';

const props = defineProps<{
    userId: number;
    name: string;
    size?: 'xs' | 'sm' | 'md';
}>();

const color = computed(() => getUserColor(props.userId));
const initials = computed(() => getUserInitials(props.name));
const sizeClass = computed(() => {
    switch (props.size) {
        case 'xs': return 'h-3.5 w-3.5 text-[7px]';
        case 'sm': return 'h-4 w-4 text-[8px]';
        default: return 'h-6 w-6 text-[10px]';
    }
});
</script>

<template>
    <div
        class="rounded-full flex items-center justify-center font-bold text-white shrink-0 select-none"
        :class="sizeClass"
        :style="{ backgroundColor: color }"
    >
        {{ initials }}
    </div>
</template>
