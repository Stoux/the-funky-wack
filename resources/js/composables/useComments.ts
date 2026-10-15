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
                const comment: Comment = { ...data.comment, parent_id: parentId ?? null };

                if (addCommentToState(livesetId, comment)) {
                    commentCounts.value[livesetId] = (commentCounts.value[livesetId] ?? 0) + 1;
                }

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
                if (markDeletedInState(livesetId, commentId)) {
                    commentCounts.value[livesetId] = Math.max(0, (commentCounts.value[livesetId] ?? 0) - 1);
                }
                return true;
            }
        } catch (error) {
            console.error('Failed to delete comment:', error);
        }
        return false;
    }

    function updateCommentInState(livesetId: number, updated: Comment) {
        const comments = commentsByLiveset.value[livesetId] ?? [];
        for (let i = 0; i < comments.length; i++) {
            if (comments[i].id === updated.id) {
                comments[i] = { ...comments[i], ...updated };
                return;
            }
            if (comments[i].replies) {
                for (let j = 0; j < comments[i].replies!.length; j++) {
                    if (comments[i].replies![j].id === updated.id) {
                        comments[i].replies![j] = { ...comments[i].replies![j], ...updated };
                        return;
                    }
                }
            }
        }
    }

    /**
     * Add a comment to the state unless it's already there (own post, or the same event twice).
     * Comments for a liveset that isn't loaded yet only bump the count, a partial list would block ensureLoaded.
     */
    function addCommentToState(livesetId: number, comment: Comment): boolean {
        const comments = commentsByLiveset.value[livesetId];
        if (comments === undefined) {
            return true;
        }

        if (comment.parent_id) {
            const parent = comments.find(c => c.id === comment.parent_id);
            if (!parent || parent.replies?.some(r => r.id === comment.id)) {
                return false;
            }
            parent.replies = [...(parent.replies ?? []), comment];
            return true;
        }

        if (comments.some(c => c.id === comment.id)) {
            return false;
        }
        commentsByLiveset.value[livesetId] = [...comments, comment];
        return true;
    }

    /**
     * Turn a comment into a [deleted] placeholder, returns false if it already was one (or isn't loaded)
     */
    function markDeletedInState(livesetId: number, commentId: number): boolean {
        for (const comment of commentsByLiveset.value[livesetId] ?? []) {
            const target = comment.id === commentId ? comment : comment.replies?.find(r => r.id === commentId);
            if (target) {
                if (target.is_deleted) {
                    return false;
                }
                target.is_deleted = true;
                target.body = null;
                return true;
            }
        }
        return false;
    }

    function addBroadcastComment(livesetId: number, comment: Comment) {
        if (addCommentToState(livesetId, comment)) {
            commentCounts.value[livesetId] = (commentCounts.value[livesetId] ?? 0) + 1;
            lastIncomingComment.value = { livesetId, comment };
        }
    }

    /**
     * Follow new, edited and deleted comments of a liveset live. Ref-counted, every subscribe needs an unsubscribe.
     */
    function subscribeToLiveset(livesetId: number): void {
        livesetSubscriptions[livesetId] = (livesetSubscriptions[livesetId] ?? 0) + 1;
        if (livesetSubscriptions[livesetId] > 1) {
            return;
        }

        echo().channel(`comments.liveset.${livesetId}`)
            .listen('.comment.posted', (comment: Comment) => addBroadcastComment(livesetId, comment))
            .listen('.comment.updated', (comment: Comment) => updateCommentInState(livesetId, comment))
            .listen('.comment.deleted', (comment: Comment) => {
                if (markDeletedInState(livesetId, comment.id)) {
                    commentCounts.value[livesetId] = Math.max(0, (commentCounts.value[livesetId] ?? 0) - 1);
                }
            });
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
        addBroadcastComment,
        subscribeToLiveset,
        unsubscribeFromLiveset,
        lastIncomingComment,
    };
}
