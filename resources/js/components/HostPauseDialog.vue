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
import { useListenAlong } from '@/composables/useListenAlong';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'stay'): void;
    (e: 'leave'): void;
    (e: 'update:open', value: boolean): void;
}>();

const { hostName, detach } = useListenAlong();

function handleStay(): void {
    emit('stay');
    emit('update:open', false);
}

async function handleLeave(): Promise<void> {
    await detach();
    emit('leave');
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Host paused</DialogTitle>
                <DialogDescription>
                    {{ hostName || 'The host' }} paused playback. Stay in sync and wait, or leave the session and keep playing on your own.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="flex flex-col gap-2 sm:flex-row">
                <Button variant="outline" @click="handleLeave">
                    Leave session
                </Button>
                <Button @click="handleStay">
                    Stay in sync
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
