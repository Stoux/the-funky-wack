<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { formatDuration } from '@/lib/utils';

interface CommentRow {
    id: number;
    user: { id: number; name: string } | null;
    liveset: { id: number; title: string } | null;
    body: string;
    timestamp_seconds: number;
    is_deleted: boolean;
    created_at: string;
}

defineProps<{
    comments: CommentRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Comments',
        href: route('admin.comments'),
    },
];

const deleteComment = (id: number) => {
    router.delete(route('admin.comments.delete', id));
};

const restoreComment = (id: number) => {
    router.post(route('admin.comments.restore', id));
};
</script>

<template>
    <Head title="Comments" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Comments</h1>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <th class="p-4 text-left">User</th>
                            <th class="p-4 text-left">Liveset</th>
                            <th class="p-4 text-left">Body</th>
                            <th class="p-4 text-left">Time</th>
                            <th class="p-4 text-left">Posted</th>
                            <th class="p-4 text-left">Status</th>
                            <th class="p-4 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="comment in comments"
                            :key="comment.id"
                            class="border-b border-sidebar-border/70 dark:border-sidebar-border"
                        >
                            <td class="p-4">{{ comment.user?.name ?? '—' }}</td>
                            <td class="p-4 max-w-32 truncate">{{ comment.liveset?.title ?? '—' }}</td>
                            <td class="p-4 max-w-48 truncate">{{ comment.body }}</td>
                            <td class="p-4 font-mono text-sm">{{ formatDuration(comment.timestamp_seconds) }}</td>
                            <td class="p-4 text-sm">{{ new Date(comment.created_at).toLocaleDateString() }}</td>
                            <td class="p-4">
                                <Badge v-if="comment.is_deleted" variant="destructive">Deleted</Badge>
                                <Badge v-else variant="outline">Active</Badge>
                            </td>
                            <td class="p-4">
                                <div class="flex gap-2">
                                    <Button
                                        v-if="comment.is_deleted"
                                        variant="outline"
                                        size="sm"
                                        @click="restoreComment(comment.id)"
                                    >
                                        Restore
                                    </Button>
                                    <ConfirmDialog
                                        v-else
                                        title="Delete this comment?"
                                        description="The comment will be soft-deleted and can be restored."
                                        confirm-label="Delete"
                                        @confirm="deleteComment(comment.id)"
                                    >
                                        <Button variant="destructive" size="sm">Delete</Button>
                                    </ConfirmDialog>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="comments.length === 0">
                            <td colspan="7" class="p-4 text-center">No comments found</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
