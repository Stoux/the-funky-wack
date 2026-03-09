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
}>();

function dismiss(): void {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Session ended</DialogTitle>
                <DialogDescription>
                    {{ hostName }} stopped playing. Your playback will continue independently.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter>
                <Button @click="dismiss">OK</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
