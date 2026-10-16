import { effectScope, ref, watch } from 'vue';
import { useAuth } from './useAuth';
import type { TrackSummary } from '@/types';

export interface LikedTrack extends TrackSummary {
    liked_at: string;
    appearances: {
        liveset_id: number;
        liveset_title: string;
        artist_name: string;
        edition_number: string;
        edition_date: string | null;
        timestamp: number | null;
        line: string;
    }[];
}

// Shared across components: the slugs of the tracks the user liked
const likedSlugs = ref<Set<string>>(new Set());
const likedTracks = ref<LikedTrack[]>([]);
let loadedForUserId: number | null = null;
let loading: Promise<void> | null = null;
let hasUserWatcher = false;

function getCsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find(row => row.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

export function useTrackLikes() {
    const { user, isAuthenticated } = useAuth();

    // Likes belong to the user, start over after login/logout
    if (!hasUserWatcher) {
        hasUserWatcher = true;
        effectScope(true).run(() => watch(() => user.value?.id, () => {
            likedSlugs.value = new Set();
            likedTracks.value = [];
            loadedForUserId = null;
            loading = null;
        }));
    }

    async function fetchLikes(): Promise<void> {
        const response = await fetch('/api/track-likes', {
            headers: { 'Accept': 'application/json' },
            credentials: 'include',
        });
        if (!response.ok) {
            return;
        }
        const data = await response.json();
        likedTracks.value = data.tracks;
        likedSlugs.value = new Set(data.tracks.map((track: LikedTrack) => track.slug));
        loadedForUserId = user.value?.id ?? null;
    }

    /**
     * Load the likes once per user, the hearts need them
     */
    function ensureLoaded(): Promise<void> {
        if (!isAuthenticated.value || loadedForUserId === user.value?.id) {
            return Promise.resolve();
        }
        loading ??= fetchLikes().finally(() => {
            loading = null;
        });
        return loading;
    }

    /**
     * Fetch again, e.g. for the liked tracks page
     */
    function reload(): Promise<void> {
        loadedForUserId = null;
        return ensureLoaded();
    }

    function isLiked(slug: string): boolean {
        return likedSlugs.value.has(slug);
    }

    async function toggleLike(slug: string): Promise<void> {
        if (!isAuthenticated.value) {
            return;
        }

        const wasLiked = isLiked(slug);
        const setLiked = (liked: boolean) => {
            const slugs = new Set(likedSlugs.value);
            if (liked) {
                slugs.add(slug);
            } else {
                slugs.delete(slug);
            }
            likedSlugs.value = slugs;
        };

        // Optimistic, put it back when the server says no
        setLiked(!wasLiked);
        try {
            const response = await fetch(`/api/tracks/${slug}/like`, {
                method: wasLiked ? 'DELETE' : 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
            });
            if (!response.ok) {
                setLiked(wasLiked);
            }
        } catch {
            setLiked(wasLiked);
        }
    }

    return {
        likedTracks,
        ensureLoaded,
        reload,
        isLiked,
        toggleLike,
    };
}
