<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import {type BreadcrumbItem, type Edition} from '@/types';
import {Head, Link, useForm} from '@inertiajs/vue3';
import {Button} from '@/components/ui/button';
import {Label} from '@/components/ui/label';
import {Input} from '@/components/ui/input';
import {computed, onUnmounted, ref} from "vue";

const {
    edition,
} = defineProps<{
    edition: Edition;
}>();

const form = useForm({
    poster: undefined as File | undefined,
});

const handleSubmit = () => {
    form.post(route('admin.editions.poster.update', { edition: edition.id }));
};

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Editions',
        href: route('admin.editions'),
    },
    {
        title: `Edition #${edition.number}`,
        href: route('admin.editions.view', edition.id),
    },
    {
        title: 'Poster',
        href: route('admin.editions.poster', edition.id),
    }
]);

const previewUrl = ref<string | null>(null);

function onFileSelected(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) {
        return
    }
    form.poster = file;
    previewUrl.value = URL.createObjectURL(file);
}

onUnmounted(() => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
});

</script>

<template>
    <Head :title="`Edition #${edition.number}`"/>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center space-x-2">
                <h1 class="text-2xl font-bold grow">{{`Edition #${edition.number}'s Poster` }}</h1>

                <Button type="submit" :disabled="form.processing || !form.poster" @click="handleSubmit">
                    Save poster
                </Button>
            </div>

            <form @submit.prevent="handleSubmit" class="flex flex-col gap-4">
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="flex flex-col gap-2">
                            <Label for="number">Poster</Label>
                            <Input
                                id="poster"
                                @change="onFileSelected"
                                type="file"
                                accept="image/*"
                                :disabled="form.processing"
                                required
                            />
                            <div v-if="form.errors.poster" class="text-sm text-red-500">{{ form.errors.poster }}</div>
                            <div v-if="previewUrl" class="mt-2">
                                <img :src="previewUrl" alt="Selected poster preview"
                                     class="w-full h-auto rounded-lg border border-sidebar-border/70 bg-transparent px-3 py-2 dark:border-sidebar-border">
                            </div>

                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="number">Current Poster</Label>
                            <div class="" v-if="edition.poster_url">
                                <img :src="edition.poster_url" alt="Edition poster" class="w-full h-auto rounded-lg border border-sidebar-border/70 bg-transparent px-3 py-2 dark:border-sidebar-border">
                            </div>
                            <div v-else>
                                <p class="text-sm text-gray-500">No poster uploaded yet.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
