<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useListenAlong } from '@/composables/useListenAlong';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'pause'): void;
    (e: 'detach'): void;
    (e: 'update:open', value: boolean): void;
}>();

const { detach } = useListenAlong();

const AUTO_DETACH_SECONDS = 10;
const countdown = ref(AUTO_DETACH_SECONDS);
let countdownTimer: number | null = null;

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        countdown.value = AUTO_DETACH_SECONDS;
        countdownTimer = window.setInterval(() => {
            countdown.value--;
            if (countdown.value <= 0) {
                handleDetach();
            }
        }, 1000);
    } else {
        clearCountdown();
    }
});

onBeforeUnmount(() => {
    clearCountdown();
});

function clearCountdown(): void {
    if (countdownTimer) {
        clearInterval(countdownTimer);
        countdownTimer = null;
    }
}

function handlePause(): void {
    clearCountdown();
    emit('pause');
    emit('update:open', false);
}

async function handleDetach(): Promise<void> {
    clearCountdown();
    await detach();
    emit('detach');
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Host paused</DialogTitle>
                <DialogDescription>
                    The host has paused playback. What would you like to do?
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="flex flex-col gap-2 sm:flex-row">
                <Button variant="outline" @click="handlePause">
                    Pause with them
                </Button>
                <Button @click="handleDetach">
                    Keep playing (detach)
                    <span class="ml-1 text-xs opacity-70">({{ countdown }}s)</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
