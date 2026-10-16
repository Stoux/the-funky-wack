<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import InputError from "@/components/InputError.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { KeyRound } from "lucide-vue-next";
import { onBeforeUnmount, onMounted, ref } from "vue";
import { Passkeys, UserCancelledError } from "@laravel/passkeys";

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

function submit() {
    form.post(route('auth.login.post'), {
        onFinish: () => form.reset('password'),
    });
}

// Passkeys: autofill in the email field, plus a button. Only with a passkey on this device,
// otherwise the browser shows a pointless "use a phone" QR dialog.
const canUsePasskey = ref(false);
const passkeyBusy = ref(false);
const passkeyError = ref<string | null>(null);

function finishPasskeyLogin(redirect: string | undefined) {
    // Full load: the session changed, and the page needs the logged in user's data
    window.location.href = redirect ?? '/';
}

async function signInWithPasskey() {
    passkeyBusy.value = true;
    passkeyError.value = null;
    try {
        finishPasskeyLogin((await Passkeys.verify()).redirect);
    } catch (error) {
        if (!(error instanceof UserCancelledError)) {
            passkeyError.value = /\b429\b|too many/i.test(String((error as Error)?.message))
                ? 'Too many attempts, try again in a minute.'
                : 'That passkey did not work. Try again or use your password.';
        }
    } finally {
        passkeyBusy.value = false;
    }
}

onMounted(async () => {
    if (!Passkeys.isSupported()) {
        return;
    }
    canUsePasskey.value = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().catch(() => false);

    // Resolves to undefined when unsupported, or when the button above cancelled it
    const result = await Passkeys.autofill().catch(() => undefined);
    if (result) {
        finishPasskeyLogin(result.redirect);
    }
});

onBeforeUnmount(() => Passkeys.cancel());
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
                            autocomplete="email webauthn"
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

                <template v-if="canUsePasskey">
                    <div class="flex items-center gap-3 text-xs uppercase text-muted-foreground">
                        <span class="h-px flex-1 bg-border" /> or <span class="h-px flex-1 bg-border" />
                    </div>
                    <Button type="button" variant="outline" class="w-full" :disabled="passkeyBusy" @click="signInWithPasskey">
                        <KeyRound class="h-4 w-4" />
                        Sign in with passkey
                    </Button>
                    <InputError :message="passkeyError ?? undefined" />
                </template>
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
