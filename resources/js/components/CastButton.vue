<script setup lang="ts">

import {Button} from "@/components/ui/button";
import {useCastMedia} from "@/composables/useCastMedia";
import {Cast, Airplay, Loader2} from "lucide-vue-next";
import {useNowPlayingState} from "@/composables/useNowPlayingState";

const {
    loading,
} = useNowPlayingState();

const {
    canCast,
    casting,
    promptForCast,
} = useCastMedia();

const ua = navigator.userAgent;
const isAirplay = ua.includes('Safari') && !ua.includes('Chrome') && !ua.includes('CriOS');

</script>

<template>
    <Button size="icon" variant="ghost" class="h-8 w-8 rounded-full cursor-pointer"
            v-if="canCast" :disabled="loading && !casting" @click="promptForCast"
            :style="{ color: casting ? '#57ECED' : undefined }"
            title="Cast audio">
        <Loader2 class="w-4 h-4 animate-spin" v-if="casting === 'connecting'" />
        <Airplay v-else-if="isAirplay" />
        <Cast v-else />
    </Button>
</template>

<style scoped>

</style>
