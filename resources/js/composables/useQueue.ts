import { ref, computed, watch } from 'vue';
import { QueueItem, QueueSource, Edition, Liveset } from '@/types';
import { useNowPlayingState } from './useNowPlayingState';
import { useEditions } from './useEditions';
import { useAudioPlayer } from './useAudioPlayer';

const lsKey = 'tfw::queue';

// Module-level state (survives navigation)
const queueItems = ref<QueueItem[]>([]);
const queueSource = ref<QueueSource | null>(null);
const isQueueActive = ref(false);

// Track if we've set up the auto-advance watcher
let hasAutoAdvanceSetup = false;

/**
 * Load queue state from localStorage
 */
function loadState() {
    try {
        const stored = localStorage.getItem(lsKey);
        if (stored) {
            const data = JSON.parse(stored);
            queueItems.value = data.items || [];
            queueSource.value = data.source || null;
            isQueueActive.value = data.active || false;
        }
    } catch (e) {
        console.warn('[Queue] Failed to load state from localStorage:', e);
    }
}

/**
 * Save queue state to localStorage
 */
function saveState() {
    try {
        const data = {
            items: queueItems.value,
            source: queueSource.value,
            active: isQueueActive.value,
        };
        localStorage.setItem(lsKey, JSON.stringify(data));
    } catch (e) {
        console.warn('[Queue] Failed to save state to localStorage:', e);
    }
}

/**
 * Generate a unique ID for queue items
 */
function generateId(): string {
    return `${Date.now()}-${Math.random().toString(36).substring(2, 9)}`;
}

// Load state on module initialization
loadState();

export function useQueue() {
    const { currentLiveset, currentEdition, finished, autoplaying } = useNowPlayingState();
    const { findLivesetById, sortedEditions } = useEditions();
    const { playLiveset } = useAudioPlayer();

    /**
     * Next item in the queue (or null if none)
     */
    const nextInQueue = computed<QueueItem | null>(() => {
        if (queueItems.value.length === 0) {
            return null;
        }
        // Return the first item in the queue (items before current have been removed)
        return queueItems.value[0] || null;
    });

    /**
     * Queue items with resolved liveset and edition data
     */
    const queueWithLivesets = computed(() => {
        return queueItems.value.map(item => {
            const result = findLivesetById(item.livesetId);
            return {
                ...item,
                liveset: result?.liveset || null,
                edition: result?.edition || null,
            };
        }).filter(item => item.liveset !== null);
    });

    /**
     * Next item in autoplay (when autoplay is enabled and queue is empty)
     * Returns the next liveset that would play based on edition autoplay logic
     */
    const nextAutoplayItem = computed<{ liveset: Liveset; edition: Edition } | null>(() => {
        // Only show if autoplay is enabled and queue is not active
        if (!autoplaying.value || isQueueActive.value || !currentEdition.value || !currentLiveset.value) {
            return null;
        }

        // Find the next liveset in the current edition
        const nextLiveset = currentEdition.value.livesets?.find(
            liveset => (liveset.lineup_order ?? 0) > (currentLiveset.value?.lineup_order ?? 0) && liveset.files
        );

        if (nextLiveset) {
            return { liveset: nextLiveset, edition: currentEdition.value };
        }

        // Otherwise look for a liveset in the next edition(s)
        let currentIndex = sortedEditions.value.findIndex(edition => edition.id === currentEdition.value?.id);
        if (currentIndex === -1) {
            return null;
        }

        // Go to the next edition (if it exists)
        currentIndex++;

        // Loop through the next editions
        for (; currentIndex < sortedEditions.value.length; currentIndex++) {
            const nextEdition = sortedEditions.value[currentIndex];
            const nextEditionLiveset = nextEdition.livesets?.find(liveset => liveset.files);
            if (nextEditionLiveset) {
                return { liveset: nextEditionLiveset, edition: nextEdition };
            }
        }

        return null;
    });

    /**
     * Set/replace the queue with new items
     */
    function setQueue(livesetIds: number[], source: QueueSource) {
        queueItems.value = livesetIds.map(id => ({
            id: generateId(),
            livesetId: id,
            source,
        }));
        queueSource.value = source;
        isQueueActive.value = true;
        saveState();
    }

    /**
     * Clear the queue
     */
    function clearQueue() {
        queueItems.value = [];
        queueSource.value = null;
        isQueueActive.value = false;
        saveState();
    }

    /**
     * Play the next item in the queue
     */
    function playNext(): boolean {
        if (queueItems.value.length === 0) {
            isQueueActive.value = false;
            saveState();
            return false;
        }

        const next = queueItems.value[0];
        const result = findLivesetById(next.livesetId);

        if (!result) {
            // Remove invalid item and try next
            queueItems.value = queueItems.value.slice(1);
            saveState();
            return playNext();
        }

        // Remove the item we're about to play
        queueItems.value = queueItems.value.slice(1);
        saveState();

        // Play the liveset
        playLiveset(result.edition, result.liveset);
        return true;
    }

    /**
     * Remove a single item from the queue by its unique ID
     */
    function removeFromQueue(id: string) {
        queueItems.value = queueItems.value.filter(item => item.id !== id);
        if (queueItems.value.length === 0) {
            isQueueActive.value = false;
            queueSource.value = null;
        }
        saveState();
    }

    /**
     * Skip to a specific item in the queue, removing all items before it
     */
    function skipTo(id: string): boolean {
        const index = queueItems.value.findIndex(item => item.id === id);
        if (index < 0) return false;

        const item = queueItems.value[index];
        const result = findLivesetById(item.livesetId);
        if (!result) return false;

        // Remove items up to and including this one
        queueItems.value = queueItems.value.slice(index + 1);
        if (queueItems.value.length === 0) {
            isQueueActive.value = false;
            queueSource.value = null;
        }
        saveState();

        playLiveset(result.edition, result.liveset);
        return true;
    }

    /**
     * Add a single liveset to the end of the queue
     */
    function addToQueue(livesetId: number, source?: QueueSource) {
        // Don't add duplicates
        if (queueItems.value.some(item => item.livesetId === livesetId)) {
            return false;
        }

        queueItems.value.push({
            id: generateId(),
            livesetId,
            source: source || queueSource.value || { type: 'edition', editionId: 0 },
        });

        if (!isQueueActive.value) {
            isQueueActive.value = true;
            if (source) {
                queueSource.value = source;
            }
        }

        saveState();
        return true;
    }

    /**
     * Set up auto-advance watcher (called once)
     */
    function setupAutoAdvance() {
        if (hasAutoAdvanceSetup) {
            return;
        }
        hasAutoAdvanceSetup = true;

        watch(finished, (isFinished) => {
            if (isFinished && isQueueActive.value && queueItems.value.length > 0) {
                console.log('[Queue] Track finished, advancing to next in queue');
                playNext();
            }
        });
    }

    // Set up auto-advance when the composable is first used
    setupAutoAdvance();

    return {
        // State
        queueItems,
        queueSource,
        isQueueActive,

        // Computed
        nextInQueue,
        queueWithLivesets,
        nextAutoplayItem,

        // Methods
        setQueue,
        clearQueue,
        playNext,
        removeFromQueue,
        addToQueue,
        skipTo,
    };
}
