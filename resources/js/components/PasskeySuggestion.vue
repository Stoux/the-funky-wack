<script setup lang="ts">
import { ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { KeyRound, X } from 'lucide-vue-next';
import { Passkeys, UserCancelledError } from '@laravel/passkeys';
import { Button } from '@/components/ui/button';

/**
 * Right after a password login without a passkey: offer to make one, if this device can hold it
 */
const page = usePage();
const visible = ref(false);
const busy = ref(false);
const state = ref<'ask' | 'done' | 'failed'>('ask');

// The layout stays mounted from /login to the next page, so watch the flashed prop rather than onMounted
watch(() => page.props.suggestPasskey as boolean | undefined, async (suggest) => {
    if (!suggest || !Passkeys.isSupported()) {
        return;
    }
    state.value = 'ask';
    visible.value = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().catch(() => false);
}, { immediate: true });

async function addPasskey() {
    busy.value = true;
    try {
        await Passkeys.register({ name: 'My passkey' });
        state.value = 'done';
        setTimeout(() => (visible.value = false), 3000);
    } catch (error) {
        if (error instanceof UserCancelledError) {
            visible.value = false;
        } else {
            state.value = 'failed';
        }
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        leave-active-class="transition duration-200 ease-in"
        leave-to-class="translate-y-2 opacity-0"
    >
        <div
            v-if="visible"
            role="status"
            class="fixed top-4 left-1/2 z-50 flex w-[calc(100%-2rem)] max-w-md -translate-x-1/2 items-center gap-3 rounded-lg border bg-background p-3 shadow-lg"
        >
            <KeyRound class="h-5 w-5 shrink-0 text-muted-foreground" />
            <p class="flex-1 text-sm">
                <template v-if="state === 'ask'">This device supports passkeys. Sign in with your fingerprint or face next time?</template>
                <template v-else-if="state === 'done'">Passkey added, use it on the login page next time.</template>
                <template v-else>That didn't work. You can add one in your <Link :href="route('user.profile')" class="underline">profile</Link>.</template>
            </p>
            <Button v-if="state === 'ask'" size="sm" :disabled="busy" @click="addPasskey">
                {{ busy ? 'Waiting...' : 'Add passkey' }}
            </Button>
            <Button variant="ghost" size="icon" class="h-8 w-8 shrink-0" title="Not now" @click="visible = false">
                <X class="h-4 w-4" />
            </Button>
        </div>
    </Transition>
</template>
