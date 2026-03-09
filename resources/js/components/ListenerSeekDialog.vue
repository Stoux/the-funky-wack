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
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'detach'): void;
    (e: 'cancel'): void;
}>();

function handleDetach(): void {
    emit('detach');
    emit('update:open', false);
}

function handleCancel(): void {
    emit('cancel');
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Desync from host?</DialogTitle>
                <DialogDescription>
                    Seeking will detach you from the host's playback. You'll continue playing independently.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="flex flex-col gap-2 sm:flex-row">
                <Button variant="outline" @click="handleCancel">
                    Stay synced
                </Button>
                <Button @click="handleDetach">
                    Detach and seek
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
