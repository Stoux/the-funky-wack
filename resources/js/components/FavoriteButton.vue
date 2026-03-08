<script setup lang="ts">
import { computed } from 'vue';
import { Button, type ButtonVariants } from '@/components/ui/button';
import { Heart } from 'lucide-vue-next';
import { useFavorites } from '@/composables/useFavorites';
import { useAuth } from '@/composables/useAuth';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
    livesetId: number;
    buttonType?: ButtonVariants['variant'];
    showCount?: boolean;
}>();

const { isAuthenticated } = useAuth();
const { isFavorited, getFavoritesCount, toggleFavorite } = useFavorites();

const favorited = computed(() => isFavorited(props.livesetId));
const count = computed(() => getFavoritesCount(props.livesetId));

async function handleClick() {
    if (!isAuthenticated.value) {
        router.visit(route('auth.login'));
        return;
    }
    await toggleFavorite(props.livesetId);
}
</script>

<template>
    <Button
        :variant="buttonType ?? 'ghost'"
        size="icon"
        class="h-8 w-8 rounded-full"
        @click.stop="handleClick"
        :title="favorited ? 'Remove from favorites' : 'Add to favorites'"
    >
        <Heart
            class="h-4 w-4 transition-colors"
            :class="{
                'fill-red-500 text-red-500': favorited,
                'text-muted-foreground hover:text-red-500': !favorited,
            }"
        />
    </Button>
    <span v-if="showCount && count > 0" class="text-xs text-muted-foreground ml-1">
        {{ count }}
    </span>
</template>
