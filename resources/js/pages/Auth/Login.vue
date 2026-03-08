<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import InputError from "@/components/InputError.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post(route('auth.login.post'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Login" />

    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md space-y-8">
            <div class="text-center">
                <h1 class="text-3xl font-bold">Welcome back</h1>
                <p class="text-muted-foreground mt-2">Sign in to your account</p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            v-model="form.email"
                            placeholder="you@example.com"
                            autofocus
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
                            placeholder="Password"
                            autocomplete="current-password"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="flex items-center space-x-2">
                        <Checkbox
                            id="remember"
                            v-model:checked="form.remember"
                        />
                        <Label for="remember" class="text-sm font-normal cursor-pointer">
                            Remember me
                        </Label>
                    </div>
                </div>

                <Button type="submit" class="w-full" :disabled="form.processing">
                    Sign in
                </Button>
            </form>

            <p class="text-center text-sm text-muted-foreground">
                Don't have an account?
                <Link :href="route('auth.register')" class="font-medium text-primary hover:underline">
                    Register with invite code
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
