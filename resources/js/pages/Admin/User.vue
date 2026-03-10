<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { computed } from 'vue';

interface UserData {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
}

const props = defineProps<{
    user: UserData | null;
}>();

const isNew = computed(() => !props.user);

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    is_admin: props.user?.is_admin ?? false,
});

const handleSubmit = () => {
    if (isNew.value) {
        form.post(route('admin.users.store'));
    } else {
        form.patch(route('admin.users.update', props.user?.id));
    }
};

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Users',
        href: route('admin.users'),
    },
    {
        title: isNew.value ? 'New User' : props.user?.name ?? '',
        href: isNew.value ? route('admin.users.create') : route('admin.users.view', props.user?.id),
    },
]);
</script>

<template>
    <Head :title="isNew ? 'New User' : user?.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <h1 class="text-2xl font-bold">{{ isNew ? 'New User' : user?.name }}</h1>

            <form @submit.prevent="handleSubmit" class="flex flex-col gap-4">
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="flex flex-col gap-2">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="form.name" type="text" :disabled="form.processing" required />
                            <div v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="email">Email</Label>
                            <Input id="email" v-model="form.email" type="email" :disabled="form.processing" required />
                            <div v-if="form.errors.email" class="text-sm text-red-500">{{ form.errors.email }}</div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="password">Password</Label>
                            <Input
                                id="password"
                                v-model="form.password"
                                type="password"
                                :disabled="form.processing"
                                :required="isNew"
                                :placeholder="isNew ? '' : 'Leave blank to keep current'"
                            />
                            <div v-if="form.errors.password" class="text-sm text-red-500">{{ form.errors.password }}</div>
                        </div>

                        <div class="flex flex-col gap-2 mt-6">
                            <div class="flex items-center gap-2">
                                <input
                                    id="is_admin"
                                    v-model="form.is_admin"
                                    type="checkbox"
                                    class="rounded border border-sidebar-border/70 bg-transparent dark:border-sidebar-border"
                                />
                                <label for="is_admin" class="font-medium">Admin</label>
                            </div>
                            <div v-if="form.errors.is_admin" class="text-sm text-red-500">{{ form.errors.is_admin }}</div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ isNew ? 'Create User' : 'Update User' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
