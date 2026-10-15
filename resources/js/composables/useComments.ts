import { effectScope, ref, watch } from 'vue';
import { useAuth } from './useAuth';
import type { Comment } from '@/types';

// Shared state across all components
const commentsByLiveset = ref<Record<number, Comment[]>>({});
const commentCounts = ref<Record<number, number>>({});

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
                const comment = data.comment as Comment;

                // Add to state
                if (parentId) {
                    // Add reply to parent
                    const comments = commentsByLiveset.value[livesetId] ?? [];
                    for (const c of comments) {
                        if (c.id === parentId) {
                            c.replies = [...(c.replies ?? []), comment];
                            break;
                        }
                    }
                } else {
                    // Add top-level comment
                    const comments = commentsByLiveset.value[livesetId] ?? [];
                    commentsByLiveset.value[livesetId] = [...comments, comment];
                }

                // Increment count
                commentCounts.value[livesetId] = (commentCounts.value[livesetId] ?? 0) + 1;

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
                },
                credentials: 'include',
            });

            if (response.ok) {
                // Mark as deleted in state
                const comments = commentsByLiveset.value[livesetId] ?? [];
                for (const c of comments) {
                    if (c.id === commentId) {
                        c.is_deleted = true;
                        c.body = null;
                        break;
                    }
                    if (c.replies) {
                        for (const r of c.replies) {
                            if (r.id === commentId) {
                                r.is_deleted = true;
                                r.body = null;
                                break;
                            }
                        }
                    }
                }

                commentCounts.value[livesetId] = Math.max(0, (commentCounts.value[livesetId] ?? 0) - 1);
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

    function addBroadcastComment(livesetId: number, comment: Comment & { parent_id?: number }) {
        if (comment.parent_id) {
            const comments = commentsByLiveset.value[livesetId] ?? [];
            for (const c of comments) {
                if (c.id === comment.parent_id) {
                    const exists = c.replies?.some(r => r.id === comment.id);
                    if (!exists) {
                        c.replies = [...(c.replies ?? []), comment];
                    }
                    break;
                }
            }
        } else {
            const comments = commentsByLiveset.value[livesetId] ?? [];
            const exists = comments.some(c => c.id === comment.id);
            if (!exists) {
                commentsByLiveset.value[livesetId] = [...comments, comment];
            }
        }
        commentCounts.value[livesetId] = (commentCounts.value[livesetId] ?? 0) + 1;
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
    };
}
