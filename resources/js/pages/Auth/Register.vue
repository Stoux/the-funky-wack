<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import InputError from "@/components/InputError.vue";
import { Button } from "@/components/ui/button";

const props = defineProps<{
    inviteCode?: string;
}>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    invite_code: props.inviteCode ?? '',
});

function submit() {
    form.post(route('auth.register.post'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Register" />

    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md space-y-8">
            <div class="text-center">
                <h1 class="text-3xl font-bold">Create an account</h1>
                <p class="text-muted-foreground mt-2">Join with an invite code</p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="invite_code">Invite Code</Label>
                        <Input
                            id="invite_code"
                            type="text"
                            name="invite_code"
                            v-model="form.invite_code"
                            placeholder="8-character code"
                            class="uppercase tracking-wider font-mono"
                            maxlength="8"
                            autofocus
                        />
                        <InputError :message="form.errors.invite_code" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            type="text"
                            name="name"
                            v-model="form.name"
                            placeholder="Your name"
                            autocomplete="name"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            v-model="form.email"
                            placeholder="you@example.com"
                            autocomplete="email"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">Password</Label>
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            v-model="form.password"
                            placeholder="At least 8 characters"
                            autocomplete="new-password"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">Confirm Password</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            v-model="form.password_confirmation"
                            placeholder="Confirm your password"
                            autocomplete="new-password"
                        />
                    </div>
                </div>

                <Button type="submit" class="w-full" :disabled="form.processing">
                    Create account
                </Button>
            </form>

            <p class="text-center text-sm text-muted-foreground">
                Already have an account?
                <Link :href="route('auth.login')" class="font-medium text-primary hover:underline">
                    Sign in
                </Link>
            </p>

            <p class="text-center text-sm text-muted-foreground">
                <Link :href="route('home')" class="text-primary hover:underline">
                    Back to home
                </Link>
            </p>
        </div>
    </div>
</template>
