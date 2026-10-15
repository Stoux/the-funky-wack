import { effectScope, ref, watch } from 'vue';
import { echo } from '@laravel/echo-vue';
import { useAuth } from './useAuth';
import type { Comment } from '@/types';

// Shared state across all components
const commentsByLiveset = ref<Record<number, Comment[]>>({});
const commentCounts = ref<Record<number, number>>({});

// Latest comment someone else posted while we were subscribed (drives the live bubble)
const lastIncomingComment = ref<{ livesetId: number; comment: Comment } | null>(null);

// Subscriber count per liveset channel
const livesetSubscriptions: Record<number, number> = {};

let hasUserWatcher = false;

export function useComments() {
    const { user, isAuthenticated } = useAuth();

    // Comments carry per-user flags (is_own), so refetch the loaded ones after login/logout
    if (!hasUserWatcher) {
        hasUserWatcher = true;
        effectScope(true).run(() => watch(() => user.value?.id, () => {
            const loadedLivesetIds = Object.keys(commentsByLiveset.value).map(Number);
            commentsByLiveset.value = {};
            loadedLivesetIds.forEach(livesetId => fetchComments(livesetId));
        }));
    }

    function initializeCommentCounts(livesets: Array<{ id: number; comments_count?: number }>) {
        for (const liveset of livesets) {
            if (liveset.comments_count !== undefined) {
                commentCounts.value[liveset.id] = liveset.comments_count;
            }
        }
    }

    function getCommentCount(livesetId: number): number {
        return commentCounts.value[livesetId] ?? 0;
    }

    function getComments(livesetId: number): Comment[] {
        return commentsByLiveset.value[livesetId] ?? [];
    }

    function ensureLoaded(livesetId: number): void {
        if (commentsByLiveset.value[livesetId] === undefined) {
            fetchComments(livesetId);
        }
    }

    async function fetchComments(livesetId: number, sort?: 'timestamp' | 'date'): Promise<Comment[]> {
        try {
            const params = sort === 'date' ? '?sort=date' : '';
            const response = await fetch(`/api/livesets/${livesetId}/comments${params}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'include',
            });

            if (response.ok) {
                const data = await response.json();
                commentsByLiveset.value[livesetId] = data.comments;
                return data.comments;
            }
        } catch (error) {
            console.error('Failed to fetch comments:', error);
        }
        return [];
    }

    async function postComment(
        livesetId: number,
        body: string,
        timestampSeconds: number,
        parentId?: number
    ): Promise<Comment | null> {
        if (!isAuthenticated.value) {
            return null;
        }

        try {
            const response = await fetch(`/api/livesets/${livesetId}/comments`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                    ...socketHeader(),
                },
                credentials: 'include',
                body: JSON.stringify({
                    body,
                    timestamp_seconds: timestampSeconds,
                    parent_id: parentId ?? null,
                }),
            });

            if (response.ok) {
                const data = await response.json();
                const comment: Comment = data.comment;
                addComment(livesetId, comment);
                return comment;
            }
        } catch (error) {
            console.error('Failed to post comment:', error);
        }
        return null;
    }

    async function editComment(commentId: number, body: string, livesetId: number): Promise<boolean> {
        try {
            const response = await fetch(`/api/comments/${commentId}`, {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                    ...socketHeader(),
                },
                credentials: 'include',
                body: JSON.stringify({ body }),
            });

            if (response.ok) {
                const data = await response.json();
                updateCommentInState(livesetId, data.comment);
                return true;
            }
        } catch (error) {
            console.error('Failed to edit comment:', error);
        }
        return false;
    }

    async function deleteComment(commentId: number, livesetId: number): Promise<boolean> {
        try {
            const response = await fetch(`/api/comments/${commentId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                    ...socketHeader(),
                },
                credentials: 'include',
            });

            if (response.ok) {
                removeComment(livesetId, commentId);
                return true;
            }
        } catch (error) {
            console.error('Failed to delete comment:', error);
        }
        return false;
    }

    function bumpCount(livesetId: number, delta: number) {
        commentCounts.value[livesetId] = Math.max(0, (commentCounts.value[livesetId] ?? 0) + delta);
    }

    function findComment(livesetId: number, commentId: number): Comment | undefined {
        for (const comment of commentsByLiveset.value[livesetId] ?? []) {
            if (comment.id === commentId) {
                return comment;
            }
            const reply = comment.replies?.find(r => r.id === commentId);
            if (reply) {
                return reply;
            }
        }
        return undefined;
    }

    /**
     * Broadcast payloads carry no per-user is_own, derive it
     */
    function withOwnership(comment: Comment): Comment {
        return { ...comment, is_own: !!user.value && comment.user?.id === user.value.id };
    }

    function updateCommentInState(livesetId: number, updated: Comment) {
        const existing = findComment(livesetId, updated.id);
        if (existing) {
            Object.assign(existing, updated);
        }
    }

    /**
     * Add a comment and bump the count, unless it's already there (own post, or the same event twice).
     * For a liveset that isn't loaded yet only the count changes, a partial list would block ensureLoaded.
     */
    function addComment(livesetId: number, comment: Comment): boolean {
        const comments = commentsByLiveset.value[livesetId];
        if (comments !== undefined) {
            if (comment.parent_id) {
                const parent = comments.find(c => c.id === comment.parent_id);
                if (!parent || parent.replies?.some(r => r.id === comment.id)) {
                    return false;
                }
                parent.replies = [...(parent.replies ?? []), comment];
            } else {
                if (comments.some(c => c.id === comment.id)) {
                    return false;
                }
                commentsByLiveset.value[livesetId] = [...comments, comment];
            }
        }
        bumpCount(livesetId, 1);
        return true;
    }

    /**
     * Same as the index endpoint: a top-level comment without replies disappears,
     * anything else becomes a [deleted] placeholder. The count drops once.
     */
    function removeComment(livesetId: number, commentId: number): void {
        const comments = commentsByLiveset.value[livesetId];
        if (comments === undefined) {
            bumpCount(livesetId, -1);
            return;
        }

        const comment = findComment(livesetId, commentId);
        if (!comment || comment.is_deleted) {
            return;
        }

        const isTopLevel = comments.some(c => c.id === commentId);
        if (isTopLevel && !comment.replies?.length) {
            commentsByLiveset.value[livesetId] = comments.filter(c => c.id !== commentId);
        } else {
            comment.is_deleted = true;
            comment.body = null;
        }
        bumpCount(livesetId, -1);
    }

    /**
     * Un-delete the placeholder, or add the comment back if it had been dropped
     */
    function restoreComment(livesetId: number, restored: Comment): void {
        const existing = findComment(livesetId, restored.id);
        if (!existing) {
            addComment(livesetId, withOwnership(restored));
            return;
        }
        if (existing.is_deleted) {
            Object.assign(existing, restored);
            bumpCount(livesetId, 1);
        }
    }

    /**
     * Follow new, edited, deleted and restored comments of a liveset live. Ref-counted, every subscribe needs an unsubscribe.
     */
    function subscribeToLiveset(livesetId: number): void {
        livesetSubscriptions[livesetId] = (livesetSubscriptions[livesetId] ?? 0) + 1;
        if (livesetSubscriptions[livesetId] > 1) {
            return;
        }

        echo().channel(`comments.liveset.${livesetId}`)
            .listen('.comment.posted', (payload: Comment) => {
                // The queued payload is built from the current row, a quick delete can beat it
                if (payload.is_deleted) {
                    return;
                }
                const comment = withOwnership(payload);
                if (addComment(livesetId, comment) && !comment.is_own) {
                    lastIncomingComment.value = { livesetId, comment };
                }
            })
            .listen('.comment.updated', (payload: Comment) => updateCommentInState(livesetId, payload))
            .listen('.comment.deleted', (payload: Comment) => removeComment(livesetId, payload.id))
            .listen('.comment.restored', (payload: Comment) => restoreComment(livesetId, payload));
    }

    function unsubscribeFromLiveset(livesetId: number): void {
        livesetSubscriptions[livesetId] = Math.max(0, (livesetSubscriptions[livesetId] ?? 0) - 1);
        if (livesetSubscriptions[livesetId] === 0) {
            echo().leaveChannel(`comments.liveset.${livesetId}`);
        }
    }

    /**
     * Lets the server skip broadcasting our own changes back to us (toOthers)
     */
    function socketHeader(): Record<string, string> {
        const socketId = echo().socketId();
        return socketId ? { 'X-Socket-ID': socketId } : {};
    }

    function getCsrfToken(): string {
        const cookie = document.cookie
            .split('; ')
            .find(row => row.startsWith('XSRF-TOKEN='));
        return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
    }

    return {
        commentsByLiveset,
        commentCounts,
        initializeCommentCounts,
        getCommentCount,
        getComments,
        ensureLoaded,
        fetchComments,
        postComment,
        editComment,
        deleteComment,
        subscribeToLiveset,
        unsubscribeFromLiveset,
        lastIncomingComment,
    };
}
