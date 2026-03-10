<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import ConfirmDialog from '@/components/ConfirmDialog.vue';

interface UserRow {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    invite_codes_count: number;
    created_at: string;
}

defineProps<{
    users: UserRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: route('admin.users'),
    },
];

const deleteUser = (id: number) => {
    router.delete(route('admin.users.delete', id), {});
};
</script>

<template>
    <Head title="Users" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Users</h1>
                <Link :href="route('admin.users.create')">
                    <Button>Add New User</Button>
                </Link>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <th class="p-4 text-left">Name</th>
                            <th class="p-4 text-left">Email</th>
                            <th class="p-4 text-left">Admin</th>
                            <th class="p-4 text-left">Invites</th>
                            <th class="p-4 text-left">Joined</th>
                            <th class="p-4 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in users"
                            :key="user.id"
                            class="border-b border-sidebar-border/70 dark:border-sidebar-border"
                        >
                            <td class="p-4">{{ user.name }}</td>
                            <td class="p-4">{{ user.email }}</td>
                            <td class="p-4">
                                <span v-if="user.is_admin" class="text-green-600 dark:text-green-400">Yes</span>
                                <span v-else class="text-muted-foreground">No</span>
                            </td>
                            <td class="p-4">{{ user.invite_codes_count }}</td>
                            <td class="p-4">{{ new Date(user.created_at).toLocaleDateString() }}</td>
                            <td class="p-4">
                                <div class="flex gap-2">
                                    <Link :href="route('admin.users.view', user.id)">
                                        <Button variant="outline" size="sm">Edit</Button>
                                    </Link>
                                    <ConfirmDialog
                                        title="Delete this user?"
                                        description="Are you sure? This will delete the user and all their data."
                                        confirm-label="Delete"
                                        @confirm="deleteUser(user.id)"
                                    >
                                        <Button variant="destructive" size="sm">Delete</Button>
                                    </ConfirmDialog>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="users.length === 0">
                            <td colspan="6" class="p-4 text-center">No users found</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
