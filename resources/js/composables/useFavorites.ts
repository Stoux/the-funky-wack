import { ref, computed } from 'vue';
import { useAuth } from './useAuth';

interface FavoriteState {
    [livesetId: number]: boolean;
}

interface FavoriteCount {
    [livesetId: number]: number;
}

// Shared state across all components
const favoriteState = ref<FavoriteState>({});
const favoriteCounts = ref<FavoriteCount>({});

export function useFavorites() {
    const { isAuthenticated } = useAuth();

    /**
     * Initialize favorite state from API response.
     * Call this when loading editions data.
     */
    function initializeFavorites(livesets: Array<{ id: number; is_favorited?: boolean; favorites_count?: number }>) {
        for (const liveset of livesets) {
            if (liveset.is_favorited !== undefined) {
                favoriteState.value[liveset.id] = liveset.is_favorited;
            }
            if (liveset.favorites_count !== undefined) {
                favoriteCounts.value[liveset.id] = liveset.favorites_count;
            }
        }
    }

    /**
     * Check if a liveset is favorited.
     */
    function isFavorited(livesetId: number): boolean {
        return favoriteState.value[livesetId] ?? false;
    }

    /**
     * Get favorites count for a liveset.
     */
    function getFavoritesCount(livesetId: number): number {
        return favoriteCounts.value[livesetId] ?? 0;
    }

    /**
     * Toggle favorite status for a liveset.
     */
    async function toggleFavorite(livesetId: number): Promise<boolean> {
        if (!isAuthenticated.value) {
            return false;
        }

        const currentlyFavorited = isFavorited(livesetId);
        const method = currentlyFavorited ? 'DELETE' : 'POST';

        // Optimistic update
        favoriteState.value[livesetId] = !currentlyFavorited;
        favoriteCounts.value[livesetId] = (favoriteCounts.value[livesetId] ?? 0) + (currentlyFavorited ? -1 : 1);

        try {
            const response = await fetch(`/api/favorites/${livesetId}`, {
                method,
                headers: {
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
            });

            if (response.ok) {
                const data = await response.json();
                if (data.favorites_count !== undefined) {
                    favoriteCounts.value[livesetId] = data.favorites_count;
                }
                return true;
            } else {
                // Revert optimistic update
                favoriteState.value[livesetId] = currentlyFavorited;
                favoriteCounts.value[livesetId] = (favoriteCounts.value[livesetId] ?? 0) + (currentlyFavorited ? 1 : -1);
                return false;
            }
        } catch (error) {
            // Revert optimistic update
            favoriteState.value[livesetId] = currentlyFavorited;
            favoriteCounts.value[livesetId] = (favoriteCounts.value[livesetId] ?? 0) + (currentlyFavorited ? 1 : -1);
            console.error('Failed to toggle favorite:', error);
            return false;
        }
    }

    /**
     * Load user's favorites from API.
     */
    async function loadFavorites() {
        if (!isAuthenticated.value) return [];

        try {
            const response = await fetch('/api/favorites', {
                headers: {
                    'Accept': 'application/json',
                },
                credentials: 'include',
            });

            if (response.ok) {
                const data = await response.json();
                // Update local state
                for (const favorite of data.favorites || []) {
                    favoriteState.value[favorite.id] = true;
                }
                return data.favorites || [];
            }
        } catch (error) {
            console.error('Failed to load favorites:', error);
        }

        return [];
    }

    function getCsrfToken(): string {
        const cookie = document.cookie
            .split('; ')
            .find(row => row.startsWith('XSRF-TOKEN='));
        return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
    }

    return {
        favoriteState,
        favoriteCounts,
        initializeFavorites,
        isFavorited,
        getFavoritesCount,
        toggleFavorite,
        loadFavorites,
    };
}
