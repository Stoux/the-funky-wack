<script setup lang="ts">

import {formatDuration} from "@/lib/utils";
import {Pause, Play, MoreVertical, ListMusic, FileText, Share2, Heart, ListPlus} from "lucide-vue-next";
import {Button, type ButtonVariants} from "@/components/ui/button";
import LivesetTrackList from "@/components/LivesetTrackList.vue";
import {Separator} from "@/components/ui/separator";
import LivesetDescription from "@/components/LivesetDescription.vue";
import {Edition, Liveset, LivesetQuality} from "@/types";
import {computed, ref} from "vue";
import ShareLivesetButton from "@/components/ShareLivesetButton.vue";
import FavoriteButton from "@/components/FavoriteButton.vue";
import AddToPlaylistButton from "@/components/AddToPlaylistButton.vue";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {useFavorites} from "@/composables/useFavorites";
import {useAuth} from "@/composables/useAuth";
import {router} from "@inertiajs/vue3";

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

// Refs for components with dialogs/sheets
const trackListRef = ref<InstanceType<typeof LivesetTrackList> | null>(null);
const descriptionRef = ref<InstanceType<typeof LivesetDescription> | null>(null);
const shareRef = ref<InstanceType<typeof ShareLivesetButton> | null>(null);
const addToPlaylistRef = ref<InstanceType<typeof AddToPlaylistButton> | null>(null);

// Favorites for mobile menu
const { isAuthenticated } = useAuth();
const { isFavorited, toggleFavorite } = useFavorites();
const favorited = computed(() => isFavorited(props.liveset.id));

async function handleMobileFavorite() {
    if (!isAuthenticated.value) {
        router.visit(route('auth.login'));
        return;
    }
    await toggleFavorite(props.liveset.id);
}

</script>

<template>
    <div class="flex items-center p-3 rounded-lg hover:bg-muted/50 transition-colors"
         :class="{ 'bg-gray-100 dark:bg-gray-800' : isCurrent }">
        <div class="flex items-center space-x-4 flex-1 min-w-0">
            <Button size="icon" :variant="isCurrent ? 'default' : 'ghost'" class="h-8 w-8 rounded-full shrink-0" :disabled="!canPlay" @click="play()">
                <Play class="h-4 w-4" v-if="canPlay && !isPlaying" />
                <Pause class="h-4 w-4" v-if="canPlay && isPlaying" />
            </Button>

            <div class="min-w-0">
                <h3 class="font-medium truncate">
                    <span class="text-primary" v-if="liveset.timeslot">{{ liveset.timeslot }} &bull; </span>
                    <span class="text-muted-foreground" v-if="liveset.lineup_order !== null && liveset.lineup_order !== undefined">#{{ liveset.lineup_order }} &bull; </span>
                    {{ liveset.title }}
                </h3>
                <div class="flex items-baseline space-x-2 truncate">
                    <span class="text-sm text-primary">{{ liveset.artist_name }}</span>
                    <template v-if="liveset.genre">
                        <span class="text-muted-foreground text-xs">&bull;</span>
                        <span class="text-muted-foreground text-xs">{{ liveset.genre }}</span>
                    </template>
                    <template v-if="liveset.bpm">
                        <span class="text-muted-foreground text-xs">&bull;</span>
                        <span class="text-muted-foreground text-xs">BPM: {{ liveset.bpm }}</span>
                    </template>
                </div>
            </div>
        </div>

        <!-- Desktop buttons - visible on md and up -->
        <div class="hidden md:flex items-center space-x-1 mr-4 text-muted-foreground">
            <Button :variant="iconButtonType" class="h-8 w-auto p-2 rounded-full"
                    v-if="liveset.files?.lq"
                    @click="play('lq')">
                LQ
            </Button>
            <Button :variant="iconButtonType" class="h-8 w-auto p-2 rounded-full"
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

            <LivesetTrackList ref="trackListRef" :liveset="liveset" :button-type="iconButtonType" />
            <LivesetDescription ref="descriptionRef" :edition="edition" :liveset="liveset" :button-type="iconButtonType" />
            <ShareLivesetButton ref="shareRef" :edition="edition" :liveset="liveset" :button-type="iconButtonType" />
            <FavoriteButton :liveset-id="liveset.id" :button-type="iconButtonType" />
            <AddToPlaylistButton ref="addToPlaylistRef" :liveset-id="liveset.id" :button-type="iconButtonType" />
        </div>

        <!-- Mobile menu - visible below md -->
        <div class="md:hidden flex items-center space-x-2">
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon" class="h-8 w-8">
                        <MoreVertical class="h-4 w-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-48">
                    <DropdownMenuLabel>Quality</DropdownMenuLabel>
                    <DropdownMenuItem v-if="liveset.files?.lq" @click="play('lq')">
                        Play LQ
                    </DropdownMenuItem>
                    <DropdownMenuItem v-if="liveset.files?.hq" @click="play('hq')">
                        Play HQ
                    </DropdownMenuItem>
                    <DropdownMenuItem v-if="liveset.files?.lossless" @click="play('lossless')">
                        Play Lossless
                    </DropdownMenuItem>
                    <DropdownMenuItem v-if="!liveset.files" disabled class="text-muted-foreground">
                        Not available
                    </DropdownMenuItem>

                    <DropdownMenuSeparator />

                    <DropdownMenuItem v-if="liveset.tracks?.length" @click="trackListRef?.open?.()">
                        <ListMusic class="h-4 w-4 mr-2" />
                        Tracklist
                    </DropdownMenuItem>
                    <DropdownMenuItem v-if="liveset.description" @click="descriptionRef?.open?.()">
                        <FileText class="h-4 w-4 mr-2" />
                        Description
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="shareRef?.open?.()">
                        <Share2 class="h-4 w-4 mr-2" />
                        Share
                    </DropdownMenuItem>

                    <DropdownMenuSeparator />

                    <DropdownMenuItem @click="handleMobileFavorite">
                        <Heart class="h-4 w-4 mr-2" :class="{ 'fill-red-500 text-red-500': favorited }" />
                        {{ favorited ? 'Unfavorite' : 'Favorite' }}
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="addToPlaylistRef?.openMenu?.()">
                        <ListPlus class="h-4 w-4 mr-2" />
                        Add to Playlist
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

                    </div>

        <div class="text-sm text-muted-foreground ml-2 shrink-0">
            {{ formatDuration(liveset.duration_in_seconds) }}
        </div>
    </div>
</template>

<style scoped>

</style>
