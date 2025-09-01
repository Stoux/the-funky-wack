<script setup lang="ts">

import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle, SheetTrigger
} from "@/components/ui/sheet";
import {Button} from "@/components/ui/button";
import {X, Search} from "lucide-vue-next";
import {Input} from "@/components/ui/input";
import {Label} from "@/components/ui/label";
import {computed, ref, useTemplateRef, watch} from "vue";
import {IndexedTrack, IndexState, useTrackSearch} from "@/composables/useTrackSearch";
import {FuseResult} from "fuse.js";
import {ScrollArea} from "@/components/ui/scroll-area";
import TrackSearchFoundTrack from "@/components/TrackSearchFoundTrack.vue";

const {
    indexState,
    indexedCounts,
    search,
    requireIndex,
} = useTrackSearch();

const isOpen = ref(false);
const query = ref('');
const results = ref<FuseResult<IndexedTrack>[]>([]);
const timeout = ref<number|undefined>(undefined);

const searchInput = useTemplateRef<HTMLInputElement>('searchInput');

const canSearch = computed(() => indexState.value === IndexState.INDEXED);

watch(isOpen, (isOpen) => {
    if (isOpen) {
        // Super ghetto way of waiting for the animation to end
        setTimeout(() => requireIndex(), 500);
    }
})

watch(query, (query) => {
    clearTimeout(timeout.value);
    timeout.value = undefined;

    if (!query) {
        results.value = [];
        return;
    }

    timeout.value = setTimeout(() => {
        results.value = search(query);
    }, 300);
})

watch(canSearch, () => {
    if (canSearch.value) {
        // searchInput.value?.focus();
    }
})


</script>

<template>
    <Sheet v-model:open="isOpen">
        <SheetTrigger as-child>
            <div class="flex items-center space-x-2 text-muted-foreground">
                <Button size="icon" variant="ghost" class="h-8 w-8 rounded-full" id="open-track-search"
                        title="Search for tracks">
                    <Search class="h-4 w-4" />
                </Button>
                <Label class="sm:hidden">Search for tracks</Label>
            </div>
        </SheetTrigger>
        <SheetContent>
            <SheetHeader class="pt-4 pb-0">
                <SheetTitle>Search for tracks</SheetTitle>
                <SheetDescription>
                    <span v-if="indexState === IndexState.MISSING_EDITIONS" class="text-destructive">Error while building search index!</span>
                    <span v-else-if="indexState === IndexState.WAITING_FOR_INDEX || indexState === IndexState.INDEXING" class="text-muted-foreground">Indexing...</span>
                    <span v-else-if="!results.length">Indexed {{ indexedCounts.tracks }} tracks over {{ indexedCounts.livesets }} livesets ({{ indexedCounts.editions }} editions)!</span>
                    <span v-else>Found {{ results.length === 1 ? '1 track' : `${results.length} tracks` }}</span>
                </SheetDescription>
            </SheetHeader>

            <ScrollArea class="h-full flex-1 overflow-y-auto">
                <div class="flex flex-col gap-2 px-4">
                    <p v-if="canSearch && !results.length" class="text-muted-foreground">
                        Type something...
                    </p>

                    <TrackSearchFoundTrack
                        v-for="result of results" :key="result.item.track.id"
                        :result="result"
                        @close="isOpen = false"
                    />
                </div>
            </ScrollArea>

            <SheetFooter class="pb-4 pt-0">
                <div class="flex flex-col gap-2 md:col-span-2">
                    <Label for="track-search-query" class="sr-only">Search query for tracks</Label>
                    <div class="flex">
                        <Input id="track-search-query" placeholder="Search artist or title..." autofocus ref="searchInput"
                               v-model="query" :disabled="!canSearch"  />
                        <Button title="Clear search query" variant="ghost" size="icon" :disabled="!query" @click="query = ''">
                            <X class="w-4 h-4" />
                        </Button>
                    </div>
                </div>
            </SheetFooter>

        </SheetContent>
    </Sheet>
</template>

<style scoped>

</style>
