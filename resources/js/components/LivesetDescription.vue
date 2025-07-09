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

defineProps<{
    edition: Edition,
    liveset: Liveset,
    buttonType?: ButtonVariants['variant'],
}>();

</script>

<template>
    <Sheet>
        <SheetTrigger as-child>
            <Button size="icon" :variant="buttonType ?? 'outline'" class="h-8 w-8 rounded-full" :disabled="!liveset.description">
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
