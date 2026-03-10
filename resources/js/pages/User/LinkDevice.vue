<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { ArrowLeft } from 'lucide-vue-next';
import UserMenu from '@/components/UserMenu.vue';
import { ref, watch, nextTick } from 'vue';

const code = ref('');
const codeInput = ref<HTMLInputElement | null>(null);
const loading = ref(false);
const success = ref(false);
const deviceName = ref('');
const error = ref('');

watch(code, (newVal) => {
    const cleaned = newVal.toUpperCase().replace(/[^A-Z2-9]/g, '').slice(0, 6);
    if (cleaned !== newVal) {
        nextTick(() => {
            if (code.value === newVal) {
                code.value = cleaned;
            }
        });
    }
});

const fullCode = () => `TFW-${code.value.trim()}`;

async function submit(): Promise<void> {
    if (!code.value.trim()) {
        return;
    }

    loading.value = true;
    error.value = '';
    success.value = false;

    try {
        const response = await fetch(`/api/auth/device-code/${encodeURIComponent(fullCode())}/authorize`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''
                ),
            },
            credentials: 'same-origin',
        });

        const data = await response.json();

        if (response.ok) {
            success.value = true;
            deviceName.value = data.device_name;
        } else if (response.status === 404) {
            error.value = 'Code not found or expired. Please check the code and try again.';
        } else if (response.status === 409) {
            error.value = 'This code has already been used.';
        } else {
            error.value = data.message ?? 'Something went wrong. Please try again.';
        }
    } catch {
        error.value = 'Network error. Please try again.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <Head title="Link Device" />

    <div class="min-h-screen p-4">
        <div class="max-w-4xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('user.devices')">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <h1 class="text-2xl font-bold">Link a Device</h1>
                </div>
                <UserMenu />
            </div>

            <p class="text-muted-foreground">
                Enter the code shown on your TV, car display, or other device to link it to your account.
            </p>

            <div v-if="success" class="border rounded-lg p-8 bg-card space-y-4 text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                    <svg class="h-8 w-8 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <p class="text-lg font-medium">Device authorized!</p>
                <p class="text-muted-foreground">
                    <span class="font-medium text-foreground">{{ deviceName }}</span> is now linked to your account.
                </p>
                <Button as-child variant="outline" class="mt-4">
                    <Link :href="route('user.devices')">Back to Devices</Link>
                </Button>
            </div>

            <form v-else @submit.prevent="submit" class="border rounded-lg p-6 bg-card space-y-6">
                <div class="grid gap-2">
                    <Label for="code">Device Code</Label>
                    <div class="flex items-center gap-2">
                        <span class="text-2xl font-mono font-medium text-muted-foreground select-none">TFW-</span>
                        <Input
                            id="code"
                            type="text"
                            name="code"
                            v-model="code"
                            placeholder="XXXXXX"
                            autofocus
                            autocomplete="off"
                            class="text-2xl tracking-widest font-mono"
                            maxlength="6"
                        />
                    </div>
                    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
                </div>

                <Button type="submit" class="w-full" :disabled="loading || code.trim().length < 6">
                    {{ loading ? 'Authorizing...' : 'Authorize Device' }}
                </Button>
            </form>
        </div>
    </div>
</template>
