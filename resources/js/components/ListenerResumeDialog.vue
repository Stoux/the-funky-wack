<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

defineProps<{
    open: boolean;
    hostName: string;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'resync'): void;
    (e: 'detach'): void;
}>();

function handleResync(): void {
    emit('resync');
    emit('update:open', false);
}

function handleDetach(): void {
    emit('detach');
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Resume playback</DialogTitle>
                <DialogDescription>
                    You paused while listening with {{ hostName }}. They may have moved ahead.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="flex flex-col gap-2 sm:flex-row">
                <Button variant="outline" @click="handleDetach">
                    Continue where I paused
                </Button>
                <Button @click="handleResync">
                    Resync with {{ hostName }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
