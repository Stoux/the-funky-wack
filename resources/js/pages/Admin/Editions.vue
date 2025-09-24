<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import {type BreadcrumbItem, type Edition} from '@/types';
import {Head, Link, router} from '@inertiajs/vue3';
import {Button} from '@/components/ui/button';
import ConfirmDialog from "@/components/ConfirmDialog.vue";

defineProps<{
    editions: Edition[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Editions',
        href: route('admin.editions'),
    },
];

const deleteEdition = (id: number) => {
    router.delete(route('admin.editions.delete', id), {});
}

</script>

<template>
    <Head title="Editions"/>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Editions</h1>
                <Link :href="route('admin.editions.create')">
                    <Button>Add New Edition</Button>
                </Link>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full">
                    <thead>
                    <tr class="border-b border-sidebar-border/70 dark:border-sidebar-border">
                        <th class="p-4 text-left">Number</th>
                        <th class="p-4 text-left">Tag Line</th>
                        <th class="p-4 text-left">Date</th>
                        <th class="p-4 text-left">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="edition in editions" :key="edition.id"
                        class="border-b border-sidebar-border/70 dark:border-sidebar-border">
                        <td class="p-4">{{ edition.number }}</td>
                        <td class="p-4">{{ edition.tag_line }}</td>
                        <td class="p-4">
                            {{ new Date(edition.date).toLocaleDateString() }}
                            <span class="text-sm text-muted-foreground" v-if="edition.timetabler_mode"><br />Timetabler Enabled</span>
                        </td>
                        <td class="p-4">
                            <div class="flex gap-2">
                                <Link :href="route('admin.editions.view', edition.id)">
                                    <Button variant="outline" size="sm">View</Button>
                                </Link>
                                <ConfirmDialog title="Delete this edition?"
                                               description="Are you sure? This cannot be undone." confirm-label="Delete"
                                               @confirm="deleteEdition(edition.id)">
                                    <Button variant="destructive" size="sm">Delete</Button>
                                </ConfirmDialog>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="editions.length === 0">
                        <td colspan="4" class="p-4 text-center">No editions found</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
