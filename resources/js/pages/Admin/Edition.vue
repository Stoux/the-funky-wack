<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import {type BreadcrumbItem, type Edition} from '@/types';
import {Head, Link, useForm} from '@inertiajs/vue3';
import {Button} from '@/components/ui/button';
import {Label} from '@/components/ui/label';
import {Input} from '@/components/ui/input';
import {computed} from "vue";

// Format a Date for date input using local timezone (not UTC)
const formatDateLocal = (date: Date): string => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const props = defineProps<{
    edition: Edition | null;
}>();

const isNewEdition = computed(() => !props.edition);

const form = useForm({
    number: props.edition?.number ?? '',
    tag_line: props.edition?.tag_line ?? '',
    empty_note: props.edition?.empty_note ?? '',
    date: props.edition?.date ? formatDateLocal(new Date(props.edition.date)) : '',
    notes: props.edition?.notes ?? '',
    timetabler_mode: props.edition?.timetabler_mode ?? false,
});

const handleSubmit = () => {
    if (isNewEdition.value) {
        form.post(route('admin.editions.store'));
    } else {
        form.patch(route('admin.editions.update', props.edition?.id));
    }
};

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Editions',
        href: route('admin.editions'),
    },
    {
        title: isNewEdition.value ? 'New Edition' : `Edition #${props.edition?.number}`,
        href: isNewEdition.value ? route('admin.editions.create') : route('admin.editions.view', props.edition?.id),
    },
]);
</script>

<template>
    <Head :title="isNewEdition ? 'New Edition' : `Edition #${edition?.number}`"/>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center space-x-2">
                <h1 class="text-2xl font-bold grow">{{ isNewEdition ? 'New Edition' : `Edition #${edition?.number}` }}</h1>

                <Link :href="route('admin.editions.poster', edition.id)" v-if="edition">
                    <Button variant="outline" class="cursor-pointer">
                        {{ edition?.poster_url ? 'Edit poster' : 'Add poster' }}
                    </Button>
                </Link>
            </div>

            <form @submit.prevent="handleSubmit" class="flex flex-col gap-4">
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="flex flex-col gap-2">
                            <Label for="number">Number</Label>
                            <Input
                                id="number"
                                v-model="form.number"
                                type="number"
                                :disabled="form.processing"
                                required
                            />
                            <div v-if="form.errors.number" class="text-sm text-red-500">{{ form.errors.number }}</div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="tag_line">Tag Line</Label>
                            <Input
                                id="tag_line"
                                v-model="form.tag_line"
                                type="text"
                                :disabled="form.processing"
                                required
                            />
                            <div v-if="form.errors.tag_line" class="text-sm text-red-500">{{
                                    form.errors.tag_line
                                }}
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="date">Date</Label>
                            <Input
                                id="date"
                                v-model="form.date"
                                type="date"
                                :disabled="form.processing"
                                required
                            />
                            <div v-if="form.errors.date" class="text-sm text-red-500">{{ form.errors.date }}</div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="tag_line">Empty note (no livesets)</Label>
                            <Input
                                id="empty_note"
                                v-model="form.empty_note"
                                type="text"
                                :disabled="form.processing"
                            />
                            <div v-if="form.errors.empty_note" class="text-sm text-red-500">{{
                                    form.errors.empty_note
                                }}
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 mt-6 md:col-span-2">
                            <div class="flex items-center gap-2">
                                <input
                                    id="timetabler_mode"
                                    v-model="form.timetabler_mode"
                                    type="checkbox"
                                    class="rounded border border-sidebar-border/70 bg-transparent dark:border-sidebar-border"
                                />
                                <label for="timetabler_mode" class="font-medium">Timetabler Mode</label>
                            </div>

                            <span class="text-sm text-muted-foreground ml-2">
                                When enabled, start times for livesets will be automatically calculated based on their duration (if the first liveset has a timestamp set). <br />
                                It will also show a timetable like structure on the overview page instead of the normal duration(s).
                            </span>

                            <div v-if="form.errors.timetabler_mode" class="text-sm text-red-500">
                                {{ form.errors.timetabler_mode }}
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 md:col-span-2">
                            <Label for="notes">Notes</Label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="4"
                                :disabled="form.processing"
                                class="rounded-lg border border-sidebar-border/70 bg-transparent px-3 py-2 dark:border-sidebar-border"
                            ></textarea>
                            <div v-if="form.errors.notes" class="text-sm text-red-500">{{ form.errors.notes }}</div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ isNewEdition ? 'Create Edition' : 'Update Edition' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
