<script setup lang="ts">

import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription, SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger
} from "@/components/ui/sheet";
import {Button, type ButtonVariants} from "@/components/ui/button";
import {Info} from "lucide-vue-next";
import {Edition, Liveset} from "@/types";
import {ref} from "vue";

defineProps<{
    edition: Edition,
    liveset: Liveset,
    buttonType?: ButtonVariants['variant'],
}>();

const isOpen = ref(false);

function open() {
    isOpen.value = true;
}

defineExpose({ open });

</script>

<template>
    <Sheet v-model:open="isOpen">
        <SheetTrigger as-child>
            <Button size="icon" :variant="buttonType ?? 'outline'" class="h-8 w-8 rounded-full"
                    title="View description / more info"
                    :disabled="!liveset.description">
                <Info class="h-4 w-4" />
            </Button>
        </SheetTrigger>
        <SheetContent>
            <SheetHeader>
                <SheetTitle>Description</SheetTitle>
                <SheetDescription>
                    {{ liveset.title }} - TFW #{{ edition.number }}
                </SheetDescription>
            </SheetHeader>

            <div class="flex flex-col p-4">
                <p>
                    {{ liveset.description }}
                </p>
            </div>

            <SheetFooter>
                <SheetClose as-child>
                    <Button>
                        Close
                    </Button>
                </SheetClose>
            </SheetFooter>

        </SheetContent>
    </Sheet>
</template>

<style scoped>

</style>
