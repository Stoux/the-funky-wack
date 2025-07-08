<script setup lang="ts">

import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger
} from "@/components/ui/sheet";
import {TrafficCone} from "lucide-vue-next";
import {Button} from "@/components/ui/button";
import {LivesetFile} from "@/types";
import {Label} from "@/components/ui/label";
import {useForm} from "@inertiajs/vue3";
import {Input} from "@/components/ui/input";
import {computed, ref, watch} from "vue";

const props = defineProps<{
    file: LivesetFile,
    hasHq: boolean,
    hasLq: boolean,
}>();

const form = useForm({
    quality: !props.hasHq ? 'hq' : 'lq',
    path: '',
    original: '',
});

const isOpen = ref(false);

// Generate the expected name, just for show.
const newFile = computed(() => {
    const path = props.file.path;
    const noExtensionPath = path.replace(/\.[a-z0-9]{2,4}$/, '');
    return noExtensionPath + '.' + form.quality + '.opus';
});

function convert() {
    form.post(route('admin.livesets.files.convert', [ props.file.liveset_id, props.file.id ] ), {
        onSuccess: () => {
            form.reset();
            form.clearErrors();
            isOpen.value = false;
        }
    });
}

</script>

<template>
    <Sheet v-model:open="isOpen">
        <SheetTrigger as-child>
            <Button
                variant="outline"
                size="icon"
                class="h-8 w-8"
                title="Convert to different quality">
                <TrafficCone class="h-4 w-4"/>
            </Button>
        </SheetTrigger>
        <SheetContent :disable-outside-pointer-events="form.processing">
            <SheetHeader>
                <SheetTitle>Convert to a different quality?</SheetTitle>
            </SheetHeader>

            <div class="flex flex-col p-4 space-y-4">

                <div class="flex flex-col gap-2 md:col-span-2">
                    <Label for="convert-original">Original file</Label>
                    <Input id="convert-original" :model-value="file.path" readonly />
                    <div v-if="form.errors.original" class="text-sm text-red-500">
                        {{ form.errors.original }}
                    </div>
                </div>

                <div class="flex flex-col gap-2 md:col-span-2">
                    <Label for="convert-quality">Convert to quality</Label>

                    <select id="convert-quality" v-model="form.quality" :disabled="form.processing"
                        class="rounded-lg border border-sidebar-border/70 bg-transparent px-3 py-2 dark:border-sidebar-border"
                        required>
                        <option v-if="!hasHq" value="hq">
                            HQ
                        </option>
                        <option v-if="!hasLq" value="lq">
                            LQ
                        </option>
                    </select>

                    <div v-if="form.errors.quality" class="text-sm text-red-500">
                        {{ form.errors.quality }}
                    </div>
                </div>

                <div class="flex flex-col gap-2 md:col-span-2">
                    <Label for="convert-original">Target file</Label>
                    <Input id="convert-original" :model-value="newFile" readonly />
                    <div v-if="form.errors.path" class="text-sm text-red-500">
                        {{ form.errors.path }}
                    </div>
                </div>

                <p class="text-sm text-muted-foreground">
                    Converting might take a little while.
                </p>
            </div>

            <SheetFooter>
                <Button @click.prevent="convert" :disabled="form.processing">
                    Convert
                </Button>
                <SheetClose as-child>
                    <Button variant="outline" :disabled="form.processing">
                        Close
                    </Button>
                </SheetClose>
            </SheetFooter>

        </SheetContent>
    </Sheet>
</template>

<style scoped>

</style>
