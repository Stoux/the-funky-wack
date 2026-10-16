<script setup lang="ts">
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Button, type ButtonVariants } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { MessageCircle, ArrowUpDown } from 'lucide-vue-next';
import { ref, watch, computed, onBeforeUnmount } from 'vue';
import { useAuth } from '@/composables/useAuth';
import { useComments } from '@/composables/useComments';
import { useNowPlayingState } from '@/composables/useNowPlayingState';
import { cn, formatDuration } from '@/lib/utils';
import CommentItem from '@/components/CommentItem.vue';
import type { Liveset } from '@/types';

const props = defineProps<{
    liveset: Liveset;
    buttonType?: ButtonVariants['variant'];
    triggerClass?: string;
}>();

const { isAuthenticated } = useAuth();
const { fetchComments, getComments, postComment, getCommentCount, isFollowing, subscribeToLiveset, unsubscribeFromLiveset } = useComments();
const { currentTime, currentLiveset } = useNowPlayingState();

const isOpen = ref(false);
const sortBy = ref<'timestamp' | 'date'>('timestamp');
const loaded = ref(false);
const newBody = ref('');
const frozenTimestamp = ref<number | null>(null);
const replyingTo = ref<number | null>(null);
const posting = ref(false);

// Sorted here so live and own comments land in the right place, not just at the end
const comments = computed(() => [...getComments(props.liveset.id)].sort((a, b) => sortBy.value === 'timestamp'
    ? a.timestamp_seconds - b.timestamp_seconds || a.created_at.localeCompare(b.created_at)
    : a.created_at.localeCompare(b.created_at)));
const commentCount = computed(() => getCommentCount(props.liveset.id));

const currentTimestamp = computed(() => {
    if (frozenTimestamp.value !== null) {
        return frozenTimestamp.value;
    }
    if (currentLiveset.value?.id === props.liveset.id) {
        return Math.floor(currentTime.value);
    }
    return 0;
});

// Raw text while the timestamp field is being edited, null otherwise
const timestampText = ref<string | null>(null);
// A typed timestamp sticks until posting or cancelling, it doesn't follow the playhead anymore
const timestampEdited = ref(false);

const timestampInvalid = computed(() => {
    if (timestampText.value !== null && parseTimestamp(timestampText.value) === null) {
        return true;
    }
    const duration = props.liveset.duration_in_seconds;
    return !!duration && currentTimestamp.value > duration;
});

/**
 * Parse "mm:ss" or "hh:mm:ss" (or plain seconds) into seconds, null if invalid
 */
function parseTimestamp(value: string): number | null {
    const parts = value.trim().split(':');
    if (parts.length > 3 || parts.some(part => !/^\d+$/.test(part))) {
        return null;
    }
    return parts.reduce((total, part) => total * 60 + Number(part), 0);
}

function onTimestampInput(value: string) {
    timestampText.value = value;
    timestampEdited.value = true;
    const parsed = parseTimestamp(value);
    if (parsed !== null) {
        frozenTimestamp.value = parsed;
    }
}

// Follow the liveset live while the sheet is open. Refetch first unless it was already followed
// (e.g. the playing set), changes while nobody listened would be missing otherwise.
watch([isOpen, () => props.liveset.id], async ([open, livesetId], [wasOpen, previousLivesetId]) => {
    if (wasOpen && previousLivesetId) {
        unsubscribeFromLiveset(previousLivesetId);
    }
    if (!open) {
        return;
    }

    const wasFollowed = isFollowing(livesetId);
    subscribeToLiveset(livesetId);
    if (!wasFollowed || getComments(livesetId).length === 0) {
        await fetchComments(livesetId);
    }
    loaded.value = true;
});
onBeforeUnmount(() => {
    if (isOpen.value) {
        unsubscribeFromLiveset(props.liveset.id);
    }
});

function toggleSort() {
    sortBy.value = sortBy.value === 'timestamp' ? 'date' : 'timestamp';
}

function handleReply(commentId: number) {
    replyingTo.value = commentId;
    // Replies are stored at the parent's timestamp, show that instead of the playhead
    const parent = comments.value.find(comment => comment.id === commentId);
    frozenTimestamp.value = parent?.timestamp_seconds ?? currentTimestamp.value;
}

function freezeTimestamp() {
    if (frozenTimestamp.value === null) {
        frozenTimestamp.value = currentTimestamp.value;
    }
}

function unfreezeTimestamp() {
    frozenTimestamp.value = null;
    timestampText.value = null;
    timestampEdited.value = false;
}

async function submitComment() {
    if (!newBody.value.trim()) {
        return;
    }

    posting.value = true;
    const result = await postComment(
        props.liveset.id,
        newBody.value,
        currentTimestamp.value,
        replyingTo.value ?? undefined,
    );

    if (result) {
        newBody.value = '';
        replyingTo.value = null;
        unfreezeTimestamp();
    }
    posting.value = false;
}

function handleSeekTo(seconds: number) {
    if (currentLiveset.value?.id === props.liveset.id) {
        currentTime.value = seconds;
    }
}

function open() {
    isOpen.value = true;
}

function openAtTimestamp(timestamp: number) {
    frozenTimestamp.value = timestamp;
    isOpen.value = true;
}

defineExpose({ open, openAtTimestamp });
</script>

<template>
    <Sheet v-model:open="isOpen">
        <SheetTrigger as-child>
            <Button size="icon" :variant="buttonType ?? 'outline'" :class="cn('h-8 w-8 rounded-full cursor-pointer', triggerClass)" title="Comments">
                <MessageCircle class="h-4 w-4" />
            </Button>
        </SheetTrigger>
        <SheetContent>
            <SheetHeader>
                <SheetTitle>Comments</SheetTitle>
                <SheetDescription>
                    {{ liveset.title }} — {{ commentCount }} comment{{ commentCount !== 1 ? 's' : '' }}
                </SheetDescription>
            </SheetHeader>

            <div class="flex items-center justify-end px-4">
                <Button variant="ghost" size="sm" class="h-7 text-xs gap-1" @click="toggleSort">
                    <ArrowUpDown class="h-3 w-3" />
                    {{ sortBy === 'timestamp' ? 'By time' : 'By date' }}
                </Button>
            </div>

            <ScrollArea class="h-full flex-1 overflow-y-auto">
                <div class="flex flex-col px-4">
                    <CommentItem
                        v-for="comment in comments"
                        :key="comment.id"
                        :comment="comment"
                        :liveset-id="liveset.id"
                        @reply="handleReply"
                        @seek-to="handleSeekTo"
                    />

                    <div v-if="loaded && comments.length === 0" class="py-8 text-center text-muted-foreground text-sm">
                        No comments yet. Be the first!
                    </div>

                    <div v-if="!loaded" class="py-8 text-center text-muted-foreground text-sm">
                        Loading...
                    </div>
                </div>
            </ScrollArea>

            <div v-if="isAuthenticated" class="border-t px-4 py-3">
                <div v-if="replyingTo" class="text-xs text-muted-foreground mb-1 flex items-center gap-1">
                    Replying to comment
                    <button class="text-primary cursor-pointer hover:underline" @click="replyingTo = null; unfreezeTimestamp()">
                        Cancel
                    </button>
                </div>
                <div class="flex items-center gap-2 text-xs text-muted-foreground mb-1">
                    <span v-if="replyingTo" class="font-mono">{{ formatDuration(currentTimestamp) }}</span>
                    <input
                        v-else
                        type="text"
                        name="timestamp"
                        inputmode="numeric"
                        aria-label="Comment timestamp"
                        title="Timestamp (mm:ss)"
                        class="w-20 rounded border bg-background px-1.5 py-0.5 font-mono"
                        :class="timestampInvalid ? 'border-destructive text-destructive' : 'border-input'"
                        :value="timestampText ?? formatDuration(currentTimestamp)"
                        @focus="freezeTimestamp"
                        @input="onTimestampInput(($event.target as HTMLInputElement).value)"
                        @blur="timestampText = null"
                    />
                    <span v-if="timestampInvalid" class="text-destructive">
                        Use mm:ss within {{ formatDuration(liveset.duration_in_seconds) }}
                    </span>
                </div>
                <div class="flex gap-2">
                    <textarea
                        v-model="newBody"
                        name="comment"
                        aria-label="Comment"
                        placeholder="Write a comment..."
                        class="flex-1 min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm resize-none"
                        rows="2"
                        maxlength="2000"
                        @focus="freezeTimestamp"
                        @blur="!newBody.trim() && !timestampEdited && unfreezeTimestamp()"
                    />
                    <Button
                        size="sm"
                        class="self-end"
                        :disabled="!newBody.trim() || posting || timestampInvalid"
                        @click="submitComment"
                    >
                        Post
                    </Button>
                </div>
            </div>

            <SheetFooter v-if="!isAuthenticated">
                <div class="text-sm text-muted-foreground text-center py-2">
                    Log in to post comments.
                </div>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
