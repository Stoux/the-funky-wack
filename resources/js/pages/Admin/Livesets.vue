<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import {type BreadcrumbItem, type Liveset} from '@/types';
import {Head, Link, router} from '@inertiajs/vue3';
import {Button} from '@/components/ui/button';
import ConfirmDialog from "@/components/ConfirmDialog.vue";

defineProps<{
    livesets: Liveset[];
    invalidTimetables: string[],
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Livesets',
        href: route('admin.livesets'),
    },
];

// Function to format duration from seconds to MM:SS
const formatDuration = (seconds: number | null | undefined): string => {
    if (seconds === null || seconds === undefined) return '--:--';
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    return `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
};

const deleteLiveset = (liveset: Liveset) => {
    router.delete(route('admin.livesets.delete', [liveset.id]));
}

</script>

<template>
    <Head title="Livesets"/>

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Livesets</h1>
                <Link :href="route('admin.livesets.create')">
                    <Button>Add New Liveset</Button>
                </Link>
            </div>

            <div v-if="invalidTimetables.length > 0" class="flex flex-col gap-2">
                <div v-for="(message, index) in invalidTimetables" :key="index"
                     class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-600 dark:border-red-900/50 dark:bg-red-950/50 dark:text-red-400">
                    {{ message }}
                </div>
            </div>


            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full">
                    <thead>
                    <tr class="border-b border-sidebar-border/70 dark:border-sidebar-border">
                        <th class="p-4 text-left">Edition</th>
                        <th class="p-4 text-left">Order</th>
                        <th class="p-4 text-left">Artist</th>
                        <th class="p-4 text-left">Title</th>
                        <th class="p-4 text-left">Duration</th>
                        <th class="p-4 text-left">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="liveset in livesets" :key="liveset.id"
                        :class="[
                            'border-b border-sidebar-border/70 dark:border-sidebar-border',
                            liveset.edition_id % 2 === 0 ? 'bg-gray-50 dark:bg-gray-800/50' : ''
                        ]">
                        <td class="p-4">
                            <div v-if="liveset.edition">
                                <div class="font-medium">TFW #{{ liveset.edition.number }}</div>
                                <div class="text-sm text-gray-500">
                                    {{ new Date(liveset.edition.date).toLocaleDateString() }}
                                </div>
                            </div>
                            <div v-else>--</div>
                        </td>
                        <td class="p-4">{{ liveset.lineup_order }}</td>
                        <td class="p-4">{{ liveset.artist_name }}</td>
                        <td class="p-4">{{ liveset.title }}</td>

                        <td class="p-4" v-if="liveset.timeslot">
                            {{ liveset.timeslot }} <span class="text-xs text-muted-foreground">({{ formatDuration(liveset.duration_in_seconds) }})</span>
                        </td>
                        <td class="p-4" v-else>
                            {{ formatDuration(liveset.duration_in_seconds) }}
                        </td>

                        <td class="p-4">
                            <div class="flex gap-2">
                                <Link :href="route('admin.livesets.view', liveset.id)">
                                    <Button variant="outline" size="sm">View</Button>
                                </Link>
                                <Link :href="route('admin.livesets.files', liveset.id)" v-if="liveset.files?.length">
                                    <Button variant="outline" size="sm">Files ({{ liveset.files.length }})</Button>
                                </Link>
                                <ConfirmDialog title="Delete this liveset?" description="This cannot be undone."
                                               confirm-label="Delete"
                                               @confirm="deleteLiveset(liveset)">
                                    <Button variant="destructive" size="sm">Delete</Button>
                                </ConfirmDialog>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="livesets.length === 0">
                        <td colspan="5" class="p-4 text-center">No livesets found</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
