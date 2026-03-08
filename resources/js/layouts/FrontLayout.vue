<script setup lang="ts">
import ListenBar from '@/components/ListenBar.vue';
import ContinuePlayingBar from '@/components/ContinuePlayingBar.vue';
import RemoteContinueBar from '@/components/RemoteContinueBar.vue';
import PlayLinkedLivesetDialog from '@/components/PlayLinkedLivesetDialog.vue';
import { useAudioPlayer } from '@/composables/useAudioPlayer';
import { useDeviceRegistration } from '@/composables/useDeviceRegistration';

const { currentLiveset, currentEdition } = useAudioPlayer();

// Auto-register device for logged-in users
useDeviceRegistration();
</script>

<template>
    <div class="min-h-screen flex flex-col">
        <!-- Page content -->
        <main class="flex-1 transition-[padding-bottom] duration-300" :class="{ 'pb-56': currentLiveset && currentEdition }">
            <slot />
        </main>

        <!-- Persistent playbar with slide-up animation -->
        <Transition
            enter-active-class="transition-transform duration-300 ease-out"
            enter-from-class="translate-y-full"
            enter-to-class="translate-y-0"
            leave-active-class="transition-transform duration-200 ease-in"
            leave-from-class="translate-y-0"
            leave-to-class="translate-y-full"
        >
            <ListenBar v-if="currentLiveset && currentEdition" />
        </Transition>

        <!-- Continue playing bar (for restoring previous session from same device) -->
        <ContinuePlayingBar />

        <!-- Remote continue bar (for continuing from other devices) -->
        <RemoteContinueBar />

        <!-- Dialog for playing linked livesets from URL parameters -->
        <PlayLinkedLivesetDialog />
    </div>
</template>
