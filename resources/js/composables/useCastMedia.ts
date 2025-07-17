import {ref} from "vue";

type CastingState = 'connecting'|'connected'|'reconnecting'|undefined;

const canCast = ref(false);
const casting = ref<CastingState>(undefined);
const audioElement = ref<HTMLAudioElement|undefined>();

function withAudioElement(audioEl: HTMLAudioElement|undefined) {
    // Check if we didn't already init with this audio element
    if (audioEl === audioElement.value) {
        return;
    }

    // Assume we can't cast with the new audio element.
    canCast.value = false;
    casting.value = undefined;

    // No audio element? Definitely not casting.
    if (audioEl === undefined) {
        audioElement.value = undefined;
        return;
    }

    // We got our HTMLAudioElement! Does it support casting?
    audioElement.value = audioEl;
    if (!('remote' in audioEl)) {
        // Nope. No native support atleast.
        canCast.value = false;
        return;
    }

    // Check the remote availability.
    console.log('Checking availability');
    audioEl.remote.watchAvailability(available => {
        console.log('Available:', available);
        // Are we still taking about the same audio element?
        if (audioElement.value !== audioEl) {
            return;
        }

        // We might be able to cast!
        canCast.value = available;
    });

    // Register state listeners
    const withEventListener = (event: string, state: CastingState) => {
        audioEl.remote.addEventListener(event, e => {
            console.log('Event:', e);
            // Are we still taking about the same audio element?
            if (audioElement.value !== audioEl) {
                return;
            }

            casting.value = state;
        })
    }

    withEventListener('connecting', 'connecting');
    withEventListener('connect', 'connected');
    withEventListener('disconnected', undefined);
}

function promptForCast() {
    return audioElement.value?.remote.prompt();
}

export function useCastMedia() {
    return {
        canCast,
        casting,
        withAudioElement,
        promptForCast,
    }
}

