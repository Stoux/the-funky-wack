import {computed, ref} from "vue";
import {Edition, Liveset, LivesetTrack} from "@/types";
import Fuse from "fuse.js";

export enum IndexState {
    MISSING_EDITIONS,
    WAITING_FOR_INDEX,
    INDEXING,
    INDEXED,
}

export interface IndexedTrack {
    track: LivesetTrack,
    liveset: Liveset,
    edition: Edition,
}

let fuse: Fuse<IndexedTrack>|undefined = undefined;

const indexState = ref(IndexState.MISSING_EDITIONS);
const editions = ref<Edition[]|undefined>(undefined);
const indexedCounts = ref({
    editions: 0,
    livesets: 0,
    tracks: 0,
});

function withEditions(availableEditions: Edition[]) {
    editions.value = availableEditions;
    indexState.value = IndexState.WAITING_FOR_INDEX;
}

function requireIndex() {
    if (indexState.value !== IndexState.WAITING_FOR_INDEX) {
        // Already indexed or in an invalid state.
        return;
    }

    // Switch state to indexing
    indexState.value = IndexState.INDEXING;

    // Loop through all editions and index all the known tracks
    const counts = { ...indexedCounts.value };
    const allTracks: IndexedTrack[] = [];
    editions.value?.forEach(edition => {
        counts.editions++;
        edition.livesets?.forEach(liveset => {
            counts.livesets++;
            liveset.tracks?.forEach(track => {
                counts.tracks++;
                allTracks.push({
                    track,
                    liveset,
                    edition,
                });
            })
        })
    });

    // => Refresh counts
    indexedCounts.value = counts;

    // Build a fuse index
    // TODO: Optimize by saving the index in local storage?
    // TODO: Optimize by moving the indexing to a web worker / background thread?
    fuse = new Fuse(allTracks, {
        keys: [
            'track.title',
        ],
        includeScore: true,
        includeMatches: true,
        findAllMatches: true,
    });
    indexState.value = IndexState.INDEXED;
}

function search(query: string) {
    if (!fuse) {
        throw new Error('Invalid state: Search index not ready');
    }

    return fuse.search(query);
}


export function useTrackSearch() {


    return {
        indexState,
        indexedCounts,
        withEditions,
        requireIndex,
        search,
    };
}
