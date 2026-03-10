/**
 * A possible track in a TrackList that does have start/end times.
 * Might not have a title/originalTrackIndex if we don't know what's playing.
 */
export type NowPlayingTrack = {
    start_at: number,
    ends_at: number,
    title?: string,
    originalTrackIndex?: number,
    transition_start?: number,
}

export function isPlaying(track: NowPlayingTrack, now: number) {
    return now >= track.start_at && now <= track.ends_at;
}


/**
 * Determine the (possibly same) nowPlayingTrackIndex based on the current time and the tracks
 *
 * @param tracks List of NowPlaying tracks
 * @param currentTime
 * @param nowPlayingTrackIndex
 *
 * @returns The index of the now playing track, or undefined if none is playing
 */
export function determineNowPlayingTrack(
    tracks: NowPlayingTrack[],
    currentTime: number,
    nowPlayingTrackIndex: number|undefined,
): number|undefined {
    // No tracks
    if (!tracks.length) {
        return undefined;
    }

    // Check if we're still playing the previously determined track
    const nowPlayingTrack = nowPlayingTrackIndex !== undefined ? tracks[nowPlayingTrackIndex] : undefined;
    if (nowPlayingTrack && isPlaying(nowPlayingTrack, currentTime)) {
        return nowPlayingTrackIndex;
    }

    // We are not. Check if we've skipped to a previous / next track
    const nowPlayingIndex = nowPlayingTrackIndex ?? 0;
    const nowPlayingTimestamp = nowPlayingTrack?.start_at ?? 0;

    // Start from that index in the direction based on the timestamp (bigger = try next, smaller = try previous)
    const direction = currentTime > nowPlayingTimestamp ? 1 : -1;

    for (let i = nowPlayingIndex; i >= 0 && i < tracks.length; i += direction) {
        const track = tracks[i];
        if (isPlaying(track, currentTime)) {
            return i;
        }
    }

    // Didn't find a track that was playing... Shouldn't really happen, as we always have full coverage of the duration of the set.
    console.error('Did not match any playing track', nowPlayingIndex, tracks);
    return undefined;
}

/**
 * Determine all currently active track indices, accounting for transitions.
 *
 * During a transition overlap zone (transition_start → start_at), both the incoming
 * track and the previous track are considered "now playing".
 *
 * @returns Array of active NowPlayingTrack indices (usually 1, sometimes 2 during transitions)
 */
export function determineNowPlayingTracks(
    tracks: NowPlayingTrack[],
    currentTime: number,
): number[] {
    for (let i = 0; i < tracks.length; i++) {
        const track = tracks[i];

        if (!isPlaying(track, currentTime)) {
            continue;
        }

        // This track is playing. Check if the NEXT track has a transition zone
        // that also covers the current time (overlap zone).
        const next = tracks[i + 1];
        if (
            next?.transition_start !== undefined &&
            next.transition_start !== null &&
            currentTime >= next.transition_start &&
            currentTime < next.start_at
        ) {
            // We're in the overlap zone — both this track (outgoing) and next (incoming) are active
            return [i, i + 1];
        }

        return [i];
    }

    return [];
}
