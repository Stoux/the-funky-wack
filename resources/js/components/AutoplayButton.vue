<script setup lang="ts">

import {Switch} from "@/components/ui/switch";
import {Tooltip, TooltipContent, TooltipProvider, TooltipTrigger} from "@/components/ui/tooltip";
import {Label} from "@/components/ui/label";

// Autoplay state is managed by useNowPlayingState (which handles persistence)
const autoplaying = defineModel<boolean>('autoplaying', {
    default: false,
});
</script>

<template>
    <TooltipProvider>
        <Tooltip>
            <TooltipTrigger>
                <div class="flex items-center space-x-2 text-muted-foreground">
                    <Label for="autoplay" class="hidden sm:flex">Autoplay</Label>
                    <Switch id="autoplay" v-model="autoplaying" />
                    <Label for="autoplay" class="sm:hidden">Autoplay</Label>
                </div>
            </TooltipTrigger>
            <TooltipContent side="bottom">
                <p>When the currently playing liveset ends, autoplay the next liveset (from current or next edition).</p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>

</template>

<style scoped>

</style>
