<script setup lang="ts">
import { ref, computed } from 'vue';
import { formatDuration } from '@/lib/utils';
import { useAuth } from '@/composables/useAuth';
import { useComments } from '@/composables/useComments';
import { Button } from '@/components/ui/button';
import UserInitials from '@/components/UserInitials.vue';
import { MessageCircle, Pencil, Trash2, X, Check } from 'lucide-vue-next';
import type { Comment } from '@/types';

const props = defineProps<{
    comment: Comment;
    livesetId: number;
    isReply?: boolean;
}>();

const emit = defineEmits<{
    (e: 'reply', commentId: number): void;
    (e: 'seekTo', seconds: number): void;
}>();

const { user } = useAuth();
const { editComment, deleteComment } = useComments();

const isEditing = ref(false);
const editBody = ref('');
const expanded = ref(false);
const saving = ref(false);

const truncatedBody = computed(() => {
    if (!props.comment.body) {
        return null;
    }
    if (props.comment.body.length <= 200 || expanded.value) {
        return props.comment.body;
    }
    return props.comment.body.substring(0, 200) + '...';
});

const needsTruncation = computed(() => {
    return (props.comment.body?.length ?? 0) > 200;
});

function startEdit() {
    editBody.value = props.comment.body ?? '';
    isEditing.value = true;
}

async function saveEdit() {
    if (!editBody.value.trim()) {
        return;
    }
    saving.value = true;
    await editComment(props.comment.id, editBody.value, props.livesetId);
    isEditing.value = false;
    saving.value = false;
}

async function handleDelete() {
    await deleteComment(props.comment.id, props.livesetId);
}
</script>

<template>
    <div :class="['py-2', isReply ? 'pl-8' : '']">
        <div v-if="comment.is_deleted" class="text-sm text-muted-foreground italic">
            [deleted]
        </div>
        <div v-else>
            <div class="flex items-start gap-2">
                <UserInitials
                    v-if="comment.user"
                    :user-id="comment.user.id"
                    :name="comment.user.name"
                    size="md"
                />
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 text-sm">
                        <span class="font-medium">{{ comment.user?.name }}</span>
                        <button
                            class="text-xs text-primary font-mono cursor-pointer hover:underline"
                            @click="emit('seekTo', comment.timestamp_seconds)"
                        >
                            {{ formatDuration(comment.timestamp_seconds) }}
                        </button>
                        <span class="text-xs text-muted-foreground">
                            {{ new Date(comment.created_at).toLocaleDateString() }}
                        </span>
                    </div>

                    <div v-if="isEditing" class="mt-1">
                        <textarea
                            v-model="editBody"
                            name="comment"
                            aria-label="Edit comment"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm resize-none"
                            rows="2"
                            maxlength="2000"
                        />
                        <div class="flex gap-1 mt-1">
                            <Button size="sm" variant="ghost" class="h-6 px-2" @click="saveEdit" :disabled="saving">
                                <Check class="h-3 w-3" />
                            </Button>
                            <Button size="sm" variant="ghost" class="h-6 px-2" @click="isEditing = false">
                                <X class="h-3 w-3" />
                            </Button>
                        </div>
                    </div>
                    <div v-else class="text-sm mt-0.5">
                        <span>{{ truncatedBody }}</span>
                        <button
                            v-if="needsTruncation && !expanded"
                            class="text-primary text-xs ml-1 cursor-pointer hover:underline"
                            @click="expanded = true"
                        >
                            show more
                        </button>
                        <button
                            v-if="needsTruncation && expanded"
                            class="text-primary text-xs ml-1 cursor-pointer hover:underline"
                            @click="expanded = false"
                        >
                            show less
                        </button>
                    </div>

                    <div class="flex items-center gap-2 mt-1" v-if="!isReply || comment.is_own || user?.is_admin">
                        <button
                            v-if="!isReply"
                            class="text-xs text-muted-foreground hover:text-foreground cursor-pointer flex items-center gap-0.5"
                            @click="emit('reply', comment.id)"
                        >
                            <MessageCircle class="h-3 w-3" />
                            Reply
                        </button>
                        <button
                            v-if="comment.is_own"
                            class="text-xs text-muted-foreground hover:text-foreground cursor-pointer flex items-center gap-0.5"
                            @click="startEdit"
                        >
                            <Pencil class="h-3 w-3" />
                            Edit
                        </button>
                        <button
                            v-if="comment.is_own || user?.is_admin"
                            class="text-xs text-muted-foreground hover:text-destructive cursor-pointer flex items-center gap-0.5"
                            @click="handleDelete"
                        >
                            <Trash2 class="h-3 w-3" />
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Replies -->
        <div v-if="comment.replies?.length">
            <CommentItem
                v-for="reply in comment.replies"
                :key="reply.id"
                :comment="reply"
                :liveset-id="livesetId"
                :is-reply="true"
                @seek-to="emit('seekTo', $event)"
            />
        </div>
    </div>
</template>
