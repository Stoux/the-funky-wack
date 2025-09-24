<script setup lang="ts">

import {formatDuration} from "@/lib/utils";
import {Pause, Play} from "lucide-vue-next";
import {Button, type ButtonVariants} from "@/components/ui/button";
import LivesetTrackList from "@/components/LivesetTrackList.vue";
import {Separator} from "@/components/ui/separator";
import LivesetDescription from "@/components/LivesetDescription.vue";
import {Edition, Liveset, LivesetQuality} from "@/types";
import {computed} from "vue";

const emit = defineEmits<{
    (e: 'play', quality?: LivesetQuality): void,
}>();

const props = defineProps<{
    edition: Edition,
    liveset: Liveset,
    isCurrent: boolean,
    isPlaying: boolean,
}>();

const canPlay = computed(() => !!props.liveset.files)
const iconButtonType = computed<ButtonVariants['variant']>(() => props.isCurrent ? 'default' : 'outline');


const play = (quality?: LivesetQuality) => {
    emit('play', quality);
}



</script>

<template>
    <div class="flex items-center p-3 rounded-lg hover:bg-muted/50 transition-colors"
         :class="{ 'bg-gray-100 dark:bg-gray-800' : isCurrent }">
        <div class="flex items-center space-x-4 flex-1">
            <Button size="icon" :variant="isCurrent ? 'default' : 'ghost'" class="h-8 w-8 rounded-full" :disabled="!canPlay" @click="play()">
                <Play class="h-4 w-4" v-if="canPlay && !isPlaying" />
                <Pause class="h-4 w-4" v-if="canPlay && isPlaying" />
            </Button>

            <div>
                <h3 class="font-medium">
                    <span class="text-primary" v-if="liveset.timeslot">{{ liveset.timeslot }} &bull; </span>
                    <span class="text-muted-foreground" v-if="liveset.lineup_order !== null && liveset.lineup_order !== undefined">#{{ liveset.lineup_order }} &bull; </span>

                    {{ liveset.title }}
                </h3>
                <div class="flex items-baseline space-x-2">
                    <span class="text-sm text-primary">{{ liveset.artist_name }}</span>
                    <template v-if="liveset.genre">
                        <span class="text-muted-foreground text-xs">&bull;</span>
                        <span class="text-muted-foreground text-xs" v-if="liveset.genre">{{ liveset.genre }}</span>
                    </template>
                    <template v-if="liveset.bpm">
                        <span class="text-muted-foreground text-xs">&bull;</span>
                        <span class="text-muted-foreground text-xs" v-if="liveset.genre">BPM: {{ liveset.bpm }}</span>
                    </template>
                </div>
            </div>
        </div>

        <div class="flex items-center space-x-1 mr-4 text-muted-foreground">

            <Button :variant="iconButtonType" class="h-8 w-auto p-2 rounded-full hidden md:flex"
                    v-if="liveset.files?.lq"
                    @click="play('lq')">
                LQ
            </Button>
            <Button :variant="iconButtonType" class="h-8 w-auto p-2 rounded-full hidden md:flex"
                    v-if="liveset.files?.hq"
                    @click="play('hq')">
                HQ
            </Button>
            <Button :variant="iconButtonType" class="h-8 w-auto p-2 rounded-full hidden lg:flex"
                    v-if="liveset.files?.lossless"
                    @click="play('lossless')">
                WAV
            </Button>

            <Separator orientation="vertical"/>

            <LivesetTrackList :liveset="liveset" :button-type="iconButtonType" />
            <LivesetDescription :edition="edition" :liveset="liveset" :button-type="iconButtonType" />
        </div>

        <div class="text-sm text-muted-foreground">
            {{ formatDuration(liveset.duration_in_seconds) }}
        </div>
    </div>
</template>

<style scoped>

</style>
