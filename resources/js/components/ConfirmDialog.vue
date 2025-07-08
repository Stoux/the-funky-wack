<script setup lang="ts">

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent, AlertDialogDescription, AlertDialogFooter,
    AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger
} from "@/components/ui/alert-dialog";
import {Button} from "@/components/ui/button";

const emits = defineEmits<{
    (e: 'confirm'): void,
}>()

defineProps<{
    title: string,
    description?: string,
    confirmLabel?: string,
}>();

</script>

<template>
    <AlertDialog>
        <AlertDialogTrigger as-child>
            <slot>
                <Button variant="default" size="sm">Confirm</Button>
            </slot>
        </AlertDialogTrigger>
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>
                    {{ title }}
                </AlertDialogTitle>
                <AlertDialogDescription v-if="description">
                    {{ description }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction @click="emits('confirm')">
                    {{ confirmLabel ?? 'Confirm' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>

<style scoped>

</style>
