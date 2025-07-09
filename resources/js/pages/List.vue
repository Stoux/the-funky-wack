<script setup lang="ts">
import {Edition, Liveset} from '@/types';
import {Head} from '@inertiajs/vue3';
import {ref, computed} from 'vue';
import {Button} from '@/components/ui/button';
import {Play} from 'lucide-vue-next';
import {Separator} from "@/components/ui/separator";
import {formatDuration} from "@/lib/utils";
import ListenBar from "@/components/ListenBar.vue";
import LivesetTrackList from "@/components/LivesetTrackList.vue";
import LivesetDescription from "@/components/LivesetDescription.vue";

const props = defineProps<{
    editions: Edition[],
    qualities: { [key: string]: string },
}>();

// Sort editions by number in descending order (newest first)
const sortedEditions = computed(() => {
    return [...props.editions].sort((a, b) => parseInt(b.number) - parseInt(a.number));
});

// State for the currently playing liveset
const currentLiveset = ref<Liveset | undefined>(undefined);
const currentEdition = ref<Edition | undefined>(undefined);
const audioQuality = ref('hq');


// Play a liveset
const playLiveset = (edition: Edition, liveset: Liveset, quality?: string) => {
    currentEdition.value = edition;
    currentLiveset.value = liveset;
    if (quality) {
        audioQuality.value = quality;
    }
};


</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4" :class="{ 'pb-64': currentLiveset && currentEdition }">
        <h2 class="text-4xl font-bold">The Funky Wack - <span
            class="text-muted-foreground">Wacky beats, the recordings.</span></h2>

        <!-- List of editions -->
        <div class="space-y-8">
            <div v-for="edition in sortedEditions" :key="edition.id" class="space-y-4">
                <!-- Edition header -->
                <div class="border-b pb-2">
                    <h2 class="text-2xl font-bold">TFW #{{ edition.number }} - <span
                        class="text-muted-foreground">{{ edition.tag_line }}</span></h2>
                    <p class="text-sm text-muted-foreground">{{ edition.date }}</p>
                    <p class="text-sm text-muted-foreground" v-if="edition.notes">{{ edition.notes }}</p>
                </div>

                <!-- Livesets for this edition -->
                <div class="space-y-2">
                    <div v-if="!edition.livesets?.length">
                        <h3 class="font-medium">No livesets (yet).</h3>
                    </div>
                    <div v-for="liveset in edition.livesets" :key="liveset.id"
                         class="flex items-center p-3 rounded-lg hover:bg-muted/50 transition-colors">
                        <div class="flex items-center space-x-4 flex-1">
                            <Button size="icon" variant="ghost" class="h-8 w-8 rounded-full" :disabled="!liveset.files"
                                    @click="playLiveset(edition, liveset)">
                                <Play class="h-4 w-4" v-if="liveset.files"/>
                            </Button>

                            <div>
                                <h3 class="font-medium">
                                    <span class="text-muted-foreground" v-if="liveset.lineup_order !== null && liveset.lineup_order !== undefined">#{{ liveset.lineup_order }} &bull;</span>
                                    {{ liveset.title }}
                                </h3>
                                <a href="#" class="text-sm text-primary hover:underline">{{ liveset.artist_name }}</a>
                            </div>
                        </div>

                        <div class="flex items-center space-x-1 mr-4 text-muted-foreground">

                            <Button variant="outline" class="h-8 w-auto p-2 rounded-full hidden md:block"
                                    v-if="liveset.files?.lq"
                                    @click="playLiveset(edition, liveset, 'lq')">
                                LQ
                            </Button>
                            <Button variant="outline" class="h-8 w-auto p-2 rounded-full hidden md:block"
                                    v-if="liveset.files?.hq"
                                    @click="playLiveset(edition, liveset, 'hq')">
                                HQ
                            </Button>
                            <Button variant="outline" class="h-8 w-auto p-2 rounded-full hidden lg:block"
                                    v-if="liveset.files?.lossless"
                                    @click="playLiveset(edition, liveset, 'lossless')">
                                WAV
                            </Button>

                            <Separator orientation="vertical"/>

                            <LivesetTrackList :liveset="liveset"/>
                            <LivesetDescription :edition="edition" :liveset="liveset"/>

                        </div>

                        <div class="text-sm text-muted-foreground">
                            {{ formatDuration(liveset.duration_in_seconds) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sticky player bar at the bottom -->
    <ListenBar v-if="currentLiveset && currentEdition"
               :edition="currentEdition" :liveset="currentLiveset" :qualities="qualities"
               v-model:quality="audioQuality"/>

</template>
