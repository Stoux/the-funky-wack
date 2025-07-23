import {useNowPlayingState} from "@/composables/useNowPlayingState";
import {computed, ref, watch} from "vue";
import {determineNowPlayingTrack, isPlaying, NowPlayingTrack} from "@/lib/tracklist.utils";


const {
    currentLiveset,
    currentTime,
} = useNowPlayingState();

const livesetTracklist = computed(() => {
    return currentLiveset.value?.tracks ?? [];
});

/**
 * List of all sections in the liveset that have a NowPlaying track.
 * All seconds inside the liveset should be covered in this list, even if the track is empty and/or we don't know what's playing.
 */
const tracks = computed<NowPlayingTrack[]>(() => {
    const result: NowPlayingTrack[] = [];

    // Loop through tracks to find the ones that can actually be played
    for (let i = 0; i < livesetTracklist.value.length; i++) {
        const livesetTrack = livesetTracklist.value[i];

        // Skip tracks that have no timestamp
        if (livesetTrack.timestamp === undefined || livesetTrack.timestamp === null) {
            continue;
        }

        // Add 'Nothing playing' starter track if first track doesn't start @ 0 seconds
        if (result.length === 0 && livesetTrack.timestamp > 0) {
            result.push({
                start_at: 0,
                ends_at: livesetTrack.timestamp,
            });
        }

        // Update the previous track's end time
        if (result.length > 0) {
            result[result.length - 1].ends_at = livesetTrack.timestamp - 1;
        }

        // Add our track, assume till the end of the track
        result.push({
            start_at: livesetTrack.timestamp,
            ends_at: Number.MAX_SAFE_INTEGER,
            title: livesetTrack.title,
            originalTrackIndex: i,
        })
    }

    // Add 'Nothing playing' track covering the whole duration if there are no tracks
    if (!result.length) {
        result.push({
            start_at: 0,
            ends_at: Number.MAX_SAFE_INTEGER,
        });
    }

    return result;
});

const nowPlayingTrackIndex = ref<number|undefined>(undefined);

const nowPlayingTrack = computed(() => {
    if (nowPlayingTrackIndex.value === undefined) {
        return undefined;
    }

    return tracks.value[nowPlayingTrackIndex.value];
})

const onNowPlayingChange = () => {
    nowPlayingTrackIndex.value = determineNowPlayingTrack(
        tracks.value,
        currentTime.value,
        nowPlayingTrackIndex.value,
    );
};

// Update the now paying track on any changes
watch(currentLiveset, () => onNowPlayingChange());
watch(tracks, () => onNowPlayingChange());
watch(currentTime, () => onNowPlayingChange());


export function useTracklistNowPlaying() {
    return {
        nowPlayingSections: tracks,
        nowPlayingTrack,
    }
}

