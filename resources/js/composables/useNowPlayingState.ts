import {onBeforeMount, onMounted, ref, watch} from "vue";
import {Edition, Liveset, LivesetQuality} from "@/types";

const lsKey = 'tfw::recently-playing';

let hasInitialSetup = false;

const currentLiveset = ref<Liveset | undefined>(undefined);
const currentEdition = ref<Edition | undefined>(undefined);
const audioQuality = ref<LivesetQuality>('hq');
const loading = ref(false);
const playing = ref(false);
const finished = ref(false);
const autoplaying = ref(false);
const currentTime = ref(0);
const restoredState = ref<StoredState|undefined>(undefined);

export type StoredState = {
    liveset: number,
    edition: number,
    timestamp: number,
    audioQuality: LivesetQuality,
}

watch(playing, () => {
    saveState();
})

watch(finished, (isFinished) => {
    if (isFinished) {
        saveState();
    }
})

watch(currentTime, (time, oldTime) => {
    // Write every 60 seconds as extra (if onUnload fails).
    if (time > 0 && (time % 60) === 0 && !finished.value) {
        saveState();
    }
    // Or if we've jumped more than 5 seconds (probably seeking / clicking through the track)
    else if (time > 0 && oldTime && Math.abs(time - oldTime) > 5) {
        saveState();
    }
})


function saveState() {
    const liveset = currentLiveset.value;
    const edition = currentEdition.value;
    if (!liveset || !edition) {
        // No liveset has been pressed to play yet. Nothing to save and/or wipe.
        return;
    }

    // An action was taken. No way to restore that old state any more.
    restoredState.value = undefined;

    // Delete the state if we're finished or if we don't have a current liveset or edition.
    if (finished.value) {
        deleteState();
        return;
    }

    const state: StoredState = {
        liveset: liveset.id,
        edition: edition.id,
        timestamp: currentTime.value,
        audioQuality: audioQuality.value,
    }

    localStorage.setItem(lsKey, JSON.stringify(state));
}

function loadState() {
    // Load the JSON state from localStorage.
    const item = localStorage.getItem(lsKey);
    if (!item) {
        return undefined;
    }

    return JSON.parse(item) as StoredState;
}


function deleteState() {
    localStorage.removeItem(lsKey);
}

export function useNowPlayingState() {

    onBeforeMount(() => {
        if (hasInitialSetup) {
            return;
        }

        hasInitialSetup = true;

        // Attempt to save the state just before closing to keep track of the current timestamp
        window.addEventListener('beforeunload', () => {
            saveState();
        });

        // Check if we have a stored state
        const state = loadState();
        if (state) {
            restoredState.value = state;
        }
    });


    return {
        currentLiveset,
        currentEdition,
        audioQuality,
        loading,
        playing,
        finished,
        autoplaying,
        currentTime,
        restoredState,
        saveState,
        deleteState,
    }
}

