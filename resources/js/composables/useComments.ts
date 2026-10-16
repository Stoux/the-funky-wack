import { effectScope, ref, watch } from 'vue';
import { echo } from '@laravel/echo-vue';
import { useAuth } from './useAuth';
import type { Comment } from '@/types';

// Shared state across all components
const commentsByLiveset = ref<Record<number, Comment[]>>({});
// comments_count from the editions data, used until a liveset's comments are loaded
const commentCounts = ref<Record<number, number>>({});

// Latest comment someone else posted while we were subscribed (pops its waveform marker)
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

    /**
     * Same as the server's comments_count: everything that isn't deleted, replies included.
     * Derived from the loaded list, so it can't drift from what's shown.
     */
    function getCommentCount(livesetId: number): number {
        const comments = commentsByLiveset.value[livesetId];
        if (comments === undefined) {
            return commentCounts.value[livesetId] ?? 0;
        }
        return comments.reduce(
            (total, comment) => total + (comment.is_deleted ? 0 : 1) + (comment.replies ?? []).filter(reply => !reply.is_deleted).length,
            0,
        );
    }

    function getComments(livesetId: number): Comment[] {
        return commentsByLiveset.value[livesetId] ?? [];
    }

    function ensureLoaded(livesetId: number): void {
        if (commentsByLiveset.value[livesetId] === undefined) {
            fetchComments(livesetId);
        }
    }

    function isFollowing(livesetId: number): boolean {
        return (livesetSubscriptions[livesetId] ?? 0) > 0;
    }

    async function fetchComments(livesetId: number): Promise<void> {
        const idsBeforeFetch = new Set(flatten(commentsByLiveset.value[livesetId] ?? []).map(comment => comment.id));

        try {
            const response = await fetch(`/api/livesets/${livesetId}/comments`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'include',
            });

            if (response.ok) {
                const data = await response.json();
                const fetched: Comment[] = data.comments;
                const fetchedIds = new Set(flatten(fetched).map(comment => comment.id));

                // Comments posted or broadcast while the request was in flight aren't in the response yet
                const addedInFlight = flatten(commentsByLiveset.value[livesetId] ?? [])
                    .filter(comment => !idsBeforeFetch.has(comment.id) && !fetchedIds.has(comment.id))
                    .sort((a, b) => a.id - b.id);

                commentsByLiveset.value[livesetId] = fetched;
                addedInFlight.forEach(comment => applyServerState(livesetId, comment));
            }
        } catch (error) {
            console.error('Failed to fetch comments:', error);
        }
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
                applyServerState(livesetId, data.comment);
                return data.comment;
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
                applyServerState(livesetId, data.comment);
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
                const existing = findComment(livesetId, commentId);
                if (existing) {
                    applyServerState(livesetId, { ...existing, is_deleted: true, body: null });
                }
                return true;
            }
        } catch (error) {
            console.error('Failed to delete comment:', error);
        }
        return false;
    }

    function flatten(comments: Comment[]): Comment[] {
        return comments.flatMap(comment => [comment, ...(comment.replies ?? [])]);
    }

    function findComment(livesetId: number, commentId: number): Comment | undefined {
        return flatten(commentsByLiveset.value[livesetId] ?? []).find(comment => comment.id === commentId);
    }

    /**
     * Bring one comment in line with the server's state of it. Broadcasts are queued and built from the row at
     * send time, so a payload can be newer than its event name: trust is_deleted, not which event it came in.
     * Deleting follows the index endpoint: a top-level comment without replies disappears, anything else
     * becomes a [deleted] placeholder. Returns whether the comment was new.
     */
    function applyServerState(livesetId: number, payload: Comment): boolean {
        const comments = commentsByLiveset.value[livesetId];
        if (comments === undefined) {
            // Not loaded, the first fetch brings it in
            return false;
        }

        const existing = findComment(livesetId, payload.id);

        if (payload.is_deleted) {
            if (!existing) {
                return false;
            }
            const isTopLevel = comments.some(comment => comment.id === payload.id);
            if (isTopLevel && !existing.replies?.length) {
                commentsByLiveset.value[livesetId] = comments.filter(comment => comment.id !== payload.id);
            } else {
                existing.is_deleted = true;
                existing.body = null;
            }
            return false;
        }

        if (existing) {
            Object.assign(existing, payload);
            return false;
        }

        // Broadcast payloads carry no per-user is_own, derive it
        const comment: Comment = { ...payload, is_own: !!user.value && payload.user?.id === user.value.id };
        if (comment.parent_id) {
            const parent = comments.find(c => c.id === comment.parent_id);
            if (!parent) {
                // Parent was dropped locally (deleted while this reply was written), the server still shows it
                fetchComments(livesetId);
                return false;
            }
            parent.replies = [...(parent.replies ?? []), comment];
        } else {
            commentsByLiveset.value[livesetId] = [...comments, comment];
        }
        return true;
    }

    /**
     * Follow new, edited, deleted and restored comments of a liveset live. Ref-counted, every subscribe needs an unsubscribe.
     */
    function subscribeToLiveset(livesetId: number): void {
        livesetSubscriptions[livesetId] = (livesetSubscriptions[livesetId] ?? 0) + 1;
        if (livesetSubscriptions[livesetId] > 1) {
            return;
        }

        const applyPayload = (payload: Comment) => applyServerState(livesetId, payload);

        echo().channel(`comments.liveset.${livesetId}`)
            .listen('.comment.posted', (payload: Comment) => {
                const isOwn = !!user.value && payload.user?.id === user.value.id;
                if (applyServerState(livesetId, payload) && !isOwn) {
                    lastIncomingComment.value = { livesetId, comment: payload };
                }
            })
            .listen('.comment.updated', applyPayload)
            .listen('.comment.deleted', applyPayload)
            .listen('.comment.restored', applyPayload);
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
        initializeCommentCounts,
        getCommentCount,
        getComments,
        ensureLoaded,
        isFollowing,
        fetchComments,
        postComment,
        editComment,
        deleteComment,
        subscribeToLiveset,
        unsubscribeFromLiveset,
        lastIncomingComment,
    };
}
