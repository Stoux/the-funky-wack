import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Edition, Liveset, LivesetFilesByQuality } from '@/types';

// Module-level state - survives Inertia navigations
const cachedEditions = ref<Edition[]>([]);
const cachedQualities = ref<LivesetFilesByQuality | null>(null);

/**
 * Shared editions state for the application.
 * Reads from Inertia's shared props on first load, then uses cached state.
 * Available on all front-end pages automatically.
 */
export function useEditions() {
    const page = usePage();

    /**
     * All editions - cached from first page load
     */
    const editions = computed<Edition[]>(() => {
        // If props has editions, cache them (first page load)
        const propsEditions = page.props.editions as Edition[] | null;
        if (propsEditions && propsEditions.length > 0) {
            cachedEditions.value = propsEditions;
        }
        return cachedEditions.value;
    });

    /**
     * Quality labels - cached from first page load
     */
    const qualities = computed<LivesetFilesByQuality>(() => {
        const propsQualities = page.props.qualities as LivesetFilesByQuality | null;
        if (propsQualities) {
            cachedQualities.value = propsQualities;
        }
        return cachedQualities.value || {
            lq: 'Low',
            hq: 'High',
            lossless: 'Lossless',
        };
    });

    /**
     * Find a liveset by ID across all editions
     */
    function findLivesetById(livesetId: number): { edition: Edition; liveset: Liveset } | undefined {
        for (const edition of editions.value) {
            const liveset = edition.livesets?.find(l => l.id === livesetId);
            if (liveset) {
                return { edition, liveset };
            }
        }
        return undefined;
    }

    /**
     * Find an edition by ID
     */
    function findEditionById(editionId: number): Edition | undefined {
        return editions.value.find(e => e.id === editionId);
    }

    /**
     * Sorted editions (newest first)
     */
    const sortedEditions = computed(() => {
        return [...editions.value].sort((a, b) => parseInt(b.number) - parseInt(a.number));
    });

    /**
     * Check if editions have been loaded
     */
    const hasEditions = computed(() => editions.value.length > 0);

    return {
        editions,
        qualities,
        sortedEditions,
        hasEditions,
        findLivesetById,
        findEditionById,
    };
}
