<script setup lang="ts">
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { ListOrdered, Play, Trash2, X, SkipForward } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { formatDuration } from '@/lib/utils';
import { useQueue } from '@/composables/useQueue';
import { useNowPlayingState } from '@/composables/useNowPlayingState';

const isOpen = ref(false);

const {
    queueItems,
    queueSource,
    isQueueActive,
    queueWithLivesets,
    nextAutoplayItem,
    clearQueue,
    playNext,
    removeFromQueue,
    skipTo,
} = useQueue();
const { currentLiveset, autoplaying } = useNowPlayingState();

const queueCount = computed(() => queueItems.value.length);

const sourceLabel = computed(() => {
    if (!queueSource.value) return '';
    switch (queueSource.value.type) {
        case 'playlist':
            return 'Playlist';
        case 'favorites':
            return 'Favorites';
        case 'edition':
            return 'Edition';
        default:
            return '';
    }
});

function handlePlayItem(item: typeof queueWithLivesets.value[0]) {
    skipTo(item.id);
}

function handleRemoveItem(id: string) {
    removeFromQueue(id);
}

function handleClearQueue() {
    clearQueue();
}

function handleSkipToNext() {
    playNext();
}
</script>

<template>
    <Sheet v-model:open="isOpen">
        <SheetTrigger as-child>
            <Button
                size="icon"
                variant="ghost"
                class="h-8 w-8 rounded-full relative"
                title="View queue"
            >
                <ListOrdered class="h-4 w-4" />
                <span
                    v-if="queueCount > 0"
                    class="absolute -top-1 -right-1 bg-primary text-primary-foreground text-xs rounded-full h-4 min-w-4 px-1 flex items-center justify-center"
                >
                    {{ queueCount > 99 ? '99+' : queueCount }}
                </span>
            </Button>
        </SheetTrigger>
        <SheetContent>
            <SheetHeader>
                <SheetTitle>Up Next</SheetTitle>
                <SheetDescription>
                    <template v-if="isQueueActive && queueCount > 0">
                        {{ queueCount }} track{{ queueCount !== 1 ? 's' : '' }} in queue
                        <span v-if="sourceLabel" class="text-muted-foreground">
                            from {{ sourceLabel }}
                        </span>
                    </template>
                    <template v-else-if="nextAutoplayItem">
                        Autoplay is enabled
                    </template>
                    <template v-else>
                        Queue is empty
                    </template>
                </SheetDescription>
            </SheetHeader>

            <ScrollArea class="h-full flex-1 overflow-y-auto">
                <!-- Empty state (no queue and no autoplay) -->
                <div v-if="(!isQueueActive || queueCount === 0) && !nextAutoplayItem" class="flex flex-col items-center justify-center py-12 text-center">
                    <ListOrdered class="h-12 w-12 text-muted-foreground mb-4" />
                    <p class="text-muted-foreground">No tracks queued</p>
                    <p class="text-sm text-muted-foreground mt-2">
                        Play from a playlist or favorites to start a queue
                    </p>
                </div>

                <!-- Queue items and/or autoplay -->
                <div v-else class="flex flex-col">
                    <div
                        v-for="item in queueWithLivesets"
                        :key="item.id"
                        class="flex items-center gap-3 px-4 py-3 hover:bg-muted/50 transition-colors group"
                    >
                        <Button
                            size="icon"
                            variant="ghost"
                            class="h-8 w-8 rounded-full shrink-0"
                            @click="handlePlayItem(item)"
                            title="Play now"
                        >
                            <Play class="h-4 w-4" />
                        </Button>

                        <div class="flex-1 min-w-0">
                            <p class="font-medium truncate">{{ item.liveset?.title }}</p>
                            <p class="text-sm text-muted-foreground truncate">
                                {{ item.liveset?.artist_name }}
                                <span v-if="item.edition" class="text-xs">
                                    &bull; TFW #{{ item.edition.number }}
                                </span>
                            </p>
                        </div>

                        <span v-if="item.liveset?.duration_in_seconds" class="text-sm text-muted-foreground shrink-0">
                            {{ formatDuration(item.liveset.duration_in_seconds) }}
                        </span>

                        <Button
                            size="icon"
                            variant="ghost"
                            class="h-8 w-8 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity"
                            @click="handleRemoveItem(item.id)"
                            title="Remove from queue"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </div>

                    <!-- Show what's next in autoplay -->
                    <template v-if="nextAutoplayItem">
                        <div class="px-4 py-2 text-xs font-medium text-muted-foreground uppercase tracking-wide" :class="{ 'border-t mt-2 pt-3': queueCount > 0 }">
                            {{ queueCount > 0 ? 'Then in autoplay' : 'Next in autoplay' }}
                        </div>
                        <div class="flex items-center gap-3 px-4 py-3 opacity-50">
                            <div class="h-8 w-8 rounded-full bg-muted shrink-0 flex items-center justify-center">
                                <Play class="h-4 w-4 text-muted-foreground" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium truncate text-muted-foreground">{{ nextAutoplayItem.liveset?.title }}</p>
                                <p class="text-sm text-muted-foreground truncate">
                                    {{ nextAutoplayItem.liveset?.artist_name }}
                                    <span class="text-xs">
                                        &bull; TFW #{{ nextAutoplayItem.edition?.number }}
                                    </span>
                                </p>
                            </div>
                            <span v-if="nextAutoplayItem.liveset?.duration_in_seconds" class="text-sm text-muted-foreground shrink-0">
                                {{ formatDuration(nextAutoplayItem.liveset.duration_in_seconds) }}
                            </span>
                        </div>
                    </template>
                </div>
            </ScrollArea>

            <SheetFooter class="flex-col gap-3">
                <!-- Autoplay toggle -->
                <div class="flex items-center justify-between w-full px-1">
                    <Label for="autoplay-toggle" class="text-sm text-muted-foreground">
                        Autoplay next liveset
                    </Label>
                    <Switch id="autoplay-toggle" v-model="autoplaying" />
                </div>

                <div class="flex gap-2 w-full">
                    <Button
                        v-if="queueCount > 0"
                        variant="default"
                        class="flex-1"
                        @click="handleSkipToNext"
                    >
                        <SkipForward class="h-4 w-4 mr-2" />
                        Next
                    </Button>
                    <Button
                        v-if="isQueueActive && queueCount > 0"
                        variant="outline"
                        size="icon"
                        @click="handleClearQueue"
                        title="Clear queue"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                    <SheetClose as-child>
                        <Button variant="outline" class="flex-1">Close</Button>
                    </SheetClose>
                </div>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
