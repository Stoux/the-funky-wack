import { ref, computed, watch, nextTick, effectScope, type EffectScope } from 'vue';
import WaveSurfer from 'wavesurfer.js';
import HoverPlugin from 'wavesurfer.js/plugins/hover';
import { Edition, Liveset, LivesetFilesByQuality, LivesetQuality } from '@/types';
import { useNowPlayingState } from './useNowPlayingState';
import { usePlaybackSync } from './usePlaybackSync';
import { useTracklistNowPlaying } from './useTracklistNowPlaying';
import { useCastMedia } from './useCastMedia';
import { formatDuration } from '@/lib/utils';
import { determineNowPlayingTrack, determineNowPlayingTracks } from '@/lib/tracklist.utils';

// Module-level state (survives navigation)
let waveInstance: WaveSurfer | undefined = undefined;
let audioElement: HTMLAudioElement | undefined = undefined;
let waveformContainer: HTMLElement | null = null;

const isInitialized = ref(false);
const loadingSource = ref<string | undefined>(undefined);

// Bumped on every (re)init and unmount. An init that awaited and finds a newer
// generation bails, so overlapping inits can never leave an orphaned WaveSurfer behind.
let initGeneration = 0;

// Player watchers are registered once, not per component calling useAudioPlayer()
let watchersScope: EffectScope | undefined = undefined;
const hasPeaks = ref<boolean | undefined>(undefined);
const generatePeaksIfMissing = ref(false);

// Track if this is the initial quality setup (vs user changing quality)
let isInitialQuality = true;
let lastQuality: string | undefined = undefined;

// When set to false, the next on('ready') will not auto-play.
// Used to join a session where the host is paused.
let autoplayOnReady = true;

// Waveform zoom: 1 = the whole set fits the width. Reset per set.
const zoomLevel = ref(1);
// While zoomed, keep the playhead in view until the user pans away. A seek turns it back on.
const followPlayhead = ref(true);
// Visible part of the waveform in seconds, for overlays like the comment markers
const visibleRange = ref<{ start: number; end: number } | null>(null);
let zoomLivesetId: number | undefined = undefined;

function resetZoomState() {
    zoomLevel.value = 1;
    followPlayhead.value = true;
    visibleRange.value = null;
}

// Quality labels
const qualityLabels: LivesetFilesByQuality = {
    lq: 'Low',
    hq: 'High',
    lossless: 'Lossless',
};

export function useAudioPlayer() {
    const {
        currentLiveset,
        currentEdition,
        audioQuality: quality,
        loading,
        playing,
        finished,
        currentTime,
    } = useNowPlayingState();

    const {
        nowPlayingSections,
        nowPlayingTrack,
        nowPlayingTracks,
    } = useTracklistNowPlaying();

    const castMedia = useCastMedia();

    const {
        startPlayback,
        setPosition: syncSetPosition,
        endPlayback,
        changeQuality: syncChangeQuality,
        onPlay: syncOnPlay,
        onPause: syncOnPause,
    } = usePlaybackSync();

    const availableQualities = computed<LivesetQuality[]>(() => {
        if (!currentLiveset.value?.files) return [];
        const keys = Object.keys(qualityLabels) as LivesetQuality[];
        return keys.filter(q => currentLiveset.value?.files?.[q] !== undefined);
    });

    const source = computed<string | undefined>(() => {
        return currentLiveset.value?.files?.[quality.value] ?? undefined;
    });

    if (!watchersScope) {
        watchersScope = effectScope(true);
        watchersScope.run(() => {
            // Watch for quality changes and sync to server
            watch(quality, (newQuality) => {
                if (isInitialQuality || !lastQuality || newQuality === lastQuality) {
                    lastQuality = newQuality;
                    return;
                }

                lastQuality = newQuality;
                syncChangeQuality(newQuality);
            });

            // Watch source changes to reinitialize player
            watch(source, () => {
                if (isInitialized.value && waveformContainer && audioElement) {
                    initPlayer();
                }
            });

            // Watch playing state to control WaveSurfer
            watch(playing, shouldBePlaying => {
                if (!waveInstance || loading.value || shouldBePlaying === waveInstance.isPlaying()) {
                    return;
                }

                if (shouldBePlaying) {
                    waveInstance.play()?.catch((e: Error) => {
                        if (e.name !== 'AbortError') throw e;
                    });
                } else {
                    waveInstance.pause();
                }
            });

            // Watch currentTime for external seeks (e.g., from tracklist click)
            watch(currentTime, shouldBeAtTime => {
                if (!waveInstance || loading.value || Math.abs(waveInstance.getCurrentTime() - shouldBeAtTime) <= 2) {
                    return;
                }

                // An external seek (tracklist, media keys, listen-along) also brings the zoomed view back to the playhead
                setFollowPlayhead(true);
                waveInstance.play(shouldBeAtTime)?.catch((e: Error) => {
                    if (e.name !== 'AbortError') throw e;
                });
            });
        });
    }

    function checkAvailableQuality() {
        if (source.value) {
            return;
        }

        console.log('[AudioPlayer] Switching quality from', quality.value);
        if (availableQualities.value.includes('hq')) {
            quality.value = 'hq';
        } else if (availableQualities.value.includes('lossless')) {
            quality.value = 'lossless';
        } else if (availableQualities.value.includes('lq')) {
            quality.value = 'lq';
        }
        console.log('[AudioPlayer] New quality', quality.value);
    }

    /**
     * Initialize or reinitialize the WaveSurfer player
     * Must be called after the DOM elements are available
     */
    async function initPlayer(force: boolean = false) {
        if (!waveformContainer || !audioElement) {
            console.warn('[AudioPlayer] Cannot init player - DOM elements not available');
            return;
        }

        if (!source.value) {
            console.log('[AudioPlayer] No source');
            return;
        }

        // Guard against concurrent calls for the same source, set immediately before any async work
        if (!force && source.value === loadingSource.value) {
            return;
        }
        const initSource = loadingSource.value = source.value;
        const generation = ++initGeneration;

        // End current playback session FIRST
        await endPlayback();
        if (generation !== initGeneration) {
            return;
        }

        destroyWaveInstance();

        playing.value = false;
        finished.value = false;
        loading.value = true;
        // Zoom belongs to a set: a rebuild of the same set (quality switch) keeps it, see the ready handler
        if (zoomLivesetId !== currentLiveset.value?.id) {
            resetZoomState();
            zoomLivesetId = currentLiveset.value?.id;
        }

        // Handle remote playback (Chromecast)
        if (castMedia.casting.value && audioElement) {
            castMedia.casting.value = 'reconnecting';
            audioElement.disableRemotePlayback = true;
        }

        // Load peaks if available
        hasPeaks.value = undefined;
        let peaks: number[][] | undefined = generatePeaksIfMissing.value ? undefined : [[]];
        if (currentLiveset.value?.audio_waveform_url) {
            try {
                const peakData: { data: number[][] } = await fetch(currentLiveset.value.audio_waveform_url).then(response => response.json());
                peaks = peakData.data;
                hasPeaks.value = true;
            } catch (e) {
                console.log('[AudioPlayer] Failed to load waveform peaks', e);
            }

            if (generation !== initGeneration || initSource !== source.value) {
                // Superseded by a newer init, source changed or unmounted. Abort loading
                return;
            }
        }
        if (hasPeaks.value === undefined) {
            hasPeaks.value = false;
        }

        let lastShownTrackIndex: number | undefined = undefined;

        const surfer = waveInstance = WaveSurfer.create({
            container: waveformContainer,
            barWidth: 1,
            barHeight: 1,
            barGap: 2,
            barAlign: 'bottom',
            progressColor: '#57ECED',
            waveColor: '#B4B7BC',
            height: 128,
            normalize: true,
            mediaControls: false,
            hideScrollbar: true,
            // Only matters once zoomed in, see setFollowPlayhead
            autoScroll: followPlayhead.value,
            autoCenter: followPlayhead.value,
            // 0 = fit the whole set in the width, zooming sets an actual px/s
            minPxPerSec: 0,
            peaks: peaks,
            url: initSource,
            media: audioElement,
            plugins: [
                HoverPlugin.create({
                    lineColor: '#ff0000',
                    lineWidth: 2,
                    labelBackground: '#555',
                    labelColor: '#fff',
                    labelSize: '11px',
                    formatTimeCallback: (seconds) => {
                        seconds = Math.floor(seconds);

                        const duration = formatDuration(seconds);

                        // Check for transition (two active tracks)
                        const activeIndices = determineNowPlayingTracks(
                            nowPlayingSections.value,
                            seconds,
                        );

                        if (activeIndices.length === 2) {
                            const outgoing = nowPlayingSections.value[activeIndices[0]];
                            const incoming = nowPlayingSections.value[activeIndices[1]];
                            if (outgoing?.title && incoming?.title) {
                                return `${duration} | ${outgoing.title} → ${incoming.title}`;
                            }
                        }

                        // Single track — use existing singular lookup for efficiency
                        lastShownTrackIndex = determineNowPlayingTrack(
                            nowPlayingSections.value,
                            seconds,
                            lastShownTrackIndex,
                        );

                        if (lastShownTrackIndex === undefined) {
                            return duration;
                        }

                        const track = nowPlayingSections.value[lastShownTrackIndex];
                        return `${duration} | ${track.title}`;
                    },
                })
            ]
        });

        // TEMP: catch duplicate waveforms in the wild. WaveSurfer mounts as a shadow-root div in the container
        const waveformCount = Array.from(waveformContainer.children).filter(child => child.shadowRoot).length;
        if (waveformCount > 1) {
            console.warn('[AudioPlayer] Duplicate waveforms detected', {
                waveformCount,
                livesetId: currentLiveset.value?.id,
                source: initSource,
                generation,
            });
        }

        surfer.on('click', () => {
            surfer.play();
            setFollowPlayhead(true);
        });

        surfer.on('scroll', (start, end) => setVisibleRange(start, end));
        // Not on 'redraw': that fires mid-render, and zooming there leaves a stale second waveform behind
        surfer.on('redrawcomplete', () => {
            // A resize keeps WaveSurfer's absolute px/s, re-apply the zoom level against the new width
            const duration = surfer.getDuration();
            const width = surfer.getWidth();
            const pxPerSec = zoomLevel.value > 1 && duration ? (width / duration) * zoomLevel.value : 0;
            if (Math.abs(pxPerSec - surfer.options.minPxPerSec) > 0.01) {
                surfer.zoom(pxPerSec);
                return;
            }
            updateVisibleRange();
        });

        surfer.on('play', () => {
            playing.value = true;
            syncOnPlay();
        });

        surfer.on('pause', () => {
            playing.value = false;
            syncOnPause();
        });

        surfer.on('ready', () => {
            console.log('[AudioPlayer] WaveSurfer ready, starting playback tracking');
            loading.value = false;

            // Handle remote playback reconnection
            if (castMedia.casting.value === 'reconnecting' && audioElement) {
                audioElement.disableRemotePlayback = false;
                castMedia.casting.value = 'connected';
                castMedia.promptForCast()?.then(() => {
                    audioElement?.play();
                });
            }

            // Mark initial quality as set
            isInitialQuality = false;
            lastQuality = quality.value;

            // Start tracking this playback session
            if (currentLiveset.value) {
                console.log('[AudioPlayer] Calling startPlayback:', currentLiveset.value.id, currentTime.value, quality.value);
                startPlayback(currentLiveset.value.id, currentTime.value, quality.value);
            }

            if (autoplayOnReady) {
                // Play at the configured time
                surfer.play(currentTime.value);
            } else {
                // Joined a paused session - position the playhead but stay paused
                surfer.setTime(currentTime.value);
                autoplayOnReady = true;
            }

            // Same set rebuilt (e.g. quality switch): put its zoom back around the playhead,
            // the new instance starts scrolled to 0 so a panned-away view can't be restored
            if (zoomLevel.value > 1) {
                const level = zoomLevel.value;
                zoomLevel.value = 1;
                setFollowPlayhead(true);
                setZoom(level);
            }

            castMedia.withAudioElement(audioElement);
        });

        surfer.on('finish', () => {
            finished.value = true;
            playing.value = false;
            endPlayback();
        });

        surfer.on('timeupdate', (time) => {
            if (loading.value) {
                return;
            }

            const flooredTime = Math.floor(time);
            currentTime.value = flooredTime;
            syncSetPosition(flooredTime);
        });
    }

    /**
     * Mount the audio player to DOM elements
     * Called by ListenBar when it mounts
     */
    function mount(container: HTMLElement, audio: HTMLAudioElement) {
        waveformContainer = container;
        audioElement = audio;
        isInitialized.value = true;

        // Initialize if we have a liveset ready
        if (currentLiveset.value) {
            checkAvailableQuality();
            initPlayer();
        }
    }

    /**
     * Unmount the visual components (WaveSurfer) but keep audio playing
     * Called by ListenBar when it unmounts during navigation
     */
    function unmount() {
        // Only destroy the visualization, not the audio
        // Audio continues playing at module level
        if (waveInstance) {
            // Detach audio element before destroying WaveSurfer so it keeps playing
            waveInstance.setOptions({ media: undefined });
        }
        destroyWaveInstance();

        // Cancel any in-flight init and allow the next mount to init the same source again
        initGeneration++;
        loadingSource.value = undefined;

        waveformContainer = null;
        isInitialized.value = false;
        // Note: audioElement is kept alive at module level, audio continues
    }

    /**
     * Destroy the current WaveSurfer instance and drop its listeners,
     * so a load still in flight can't fire our ready/finish handlers afterwards
     */
    function destroyWaveInstance() {
        waveInstance?.destroy();
        waveInstance?.unAll();
        waveInstance = undefined;
    }

    /**
     * Play a liveset
     */
    function playLiveset(edition: Edition, liveset: Liveset, qualityOverride?: LivesetQuality, atTime: number = 0, startPaused: boolean = false) {
        autoplayOnReady = !startPaused;
        // If same liveset, just toggle play/pause
        if (currentEdition.value?.id === edition.id &&
            currentLiveset.value?.id === liveset.id &&
            (qualityOverride === undefined || qualityOverride === quality.value)) {
            playing.value = !playing.value;
            return;
        }

        // Drop the old instance right away, otherwise the seek/play watchers act on it before
        // initPlayer replaces it (e.g. restarting a finished set when currentTime resets to 0)
        if (currentLiveset.value?.id !== liveset.id) {
            destroyWaveInstance();
            resetZoomState();
        }

        currentEdition.value = edition;
        currentLiveset.value = liveset;
        currentTime.value = atTime;
        if (qualityOverride) {
            quality.value = qualityOverride;
        }

        // Reset quality tracking for new liveset
        isInitialQuality = true;
        lastQuality = undefined;

        // Check available quality and initialize
        nextTick(() => {
            checkAvailableQuality();
            if (isInitialized.value) {
                initPlayer();
            }
        });
    }

    /**
     * Play/resume playback
     */
    function play() {
        playing.value = true;
    }

    /**
     * Pause playback
     */
    function pause() {
        playing.value = false;
    }

    /**
     * Toggle play/pause
     */
    function togglePlayPause() {
        playing.value = !playing.value;
    }

    /**
     * Seek to a specific time. Starts playback, unless keepPaused is set and it's paused (e.g. lockscreen scrubbing).
     */
    function seek(time: number, { keepPaused = false }: { keepPaused?: boolean } = {}) {
        if (keepPaused && waveInstance && !loading.value && !playing.value) {
            // Moves the paused playhead first, so the currentTime watcher sees no jump and doesn't play
            setFollowPlayhead(true);
            waveInstance.setTime(time);
        }
        currentTime.value = time;
    }

    /**
     * Set audio quality
     */
    function setQuality(newQuality: LivesetQuality) {
        quality.value = newQuality;
    }

    /**
     * Toggle generating peaks if missing
     */
    function toggleGeneratePeaks() {
        generatePeaksIfMissing.value = !generatePeaksIfMissing.value;
        if (isInitialized.value) {
            initPlayer(true);
        }
    }

    // Same scale as the Android app: 12x up to 30 minutes, then +12x per extra hour, capped at 48x
    const maxZoom = computed(() => {
        const minutes = (currentLiveset.value?.duration_in_seconds ?? 0) / 60;
        return Math.min(48, 12 * (1 + Math.max(0, (minutes - 30) / 60)));
    });

    /**
     * Pixels per second as actually rendered (the full waveform's width), not as intended by zoomLevel
     */
    function renderedPxPerSec(): number {
        const duration = waveInstance?.getDuration();
        if (!waveInstance || !duration) {
            return 0;
        }
        return (waveInstance.getWrapper().scrollWidth || waveInstance.getWidth()) / duration;
    }

    function setVisibleRange(start: number, end: number) {
        // Skip identical updates, scroll events fire every frame while following
        const current = visibleRange.value;
        if (current && Math.abs(current.start - start) < 0.01 && Math.abs(current.end - end) < 0.01) {
            return;
        }
        visibleRange.value = { start, end };
    }

    function updateVisibleRange() {
        const pxPerSec = renderedPxPerSec();
        const width = waveInstance?.getWidth();
        if (!waveInstance || !pxPerSec || !width) {
            return;
        }

        const start = waveInstance.getScroll() / pxPerSec;
        setVisibleRange(start, start + width / pxPerSec);
    }

    /**
     * Zoom the waveform, keeping the time under anchorX (px from the left edge) in place.
     * Without an anchor (buttons) it zooms around the playhead while following, else around the middle.
     */
    function setZoom(level: number, anchorX?: number) {
        const duration = waveInstance?.getDuration();
        const width = waveInstance?.getWidth();
        if (!waveInstance || loading.value || !duration || !width) {
            return;
        }

        const newLevel = Math.min(Math.max(level, 1), maxZoom.value);
        if (newLevel === zoomLevel.value) {
            return;
        }

        const anchor = anchorX ?? width / 2;
        const anchorTime = anchorX === undefined && followPlayhead.value
            ? waveInstance.getCurrentTime()
            : (waveInstance.getScroll() + anchor) / renderedPxPerSec();

        zoomLevel.value = newLevel;
        if (newLevel === 1) {
            // Back to the whole set: fit it again and follow by default
            setFollowPlayhead(true);
            waveInstance.zoom(0);
            updateVisibleRange();
            return;
        }

        const pxPerSec = (width / duration) * newLevel;
        const scroll = anchorTime * pxPerSec - anchor;

        // Zooming in on a spot away from the playhead means looking there, following would yank the view back
        const playheadX = waveInstance.getCurrentTime() * pxPerSec - scroll;
        if (anchorX !== undefined && (playheadX < 0 || playheadX > width)) {
            setFollowPlayhead(false);
        }

        waveInstance.zoom(pxPerSec);
        // The browser clamps this to the scrollable range, so the window never leaves the set
        waveInstance.setScroll(scroll);
        updateVisibleRange();
    }

    /**
     * Keep the playhead in view while zoomed. Turned off by panning, back on by a seek or the follow button.
     */
    function setFollowPlayhead(follow: boolean) {
        if (followPlayhead.value === follow) {
            return;
        }
        followPlayhead.value = follow;

        if (!waveInstance) {
            return;
        }

        waveInstance.setOptions({ autoScroll: follow, autoCenter: follow });
        if (follow && zoomLevel.value > 1) {
            waveInstance.setScroll(waveInstance.getCurrentTime() * renderedPxPerSec() - waveInstance.getWidth() / 2);
            updateVisibleRange();
        }
    }

    /**
     * Horizontal position (0-100%) of a timestamp within the visible part of the waveform, null if unknown
     */
    function getVisiblePosition(seconds: number): number | null {
        const range = visibleRange.value;
        if (range && range.end > range.start) {
            return ((seconds - range.start) / (range.end - range.start)) * 100;
        }

        const duration = currentLiveset.value?.duration_in_seconds;
        if (!duration) {
            return null;
        }

        return (seconds / duration) * 100;
    }

    return {
        // State
        currentLiveset,
        currentEdition,
        quality,
        loading,
        playing,
        finished,
        currentTime,
        isInitialized,
        hasPeaks,
        generatePeaksIfMissing,
        availableQualities,
        qualityLabels,
        nowPlayingTrack,
        nowPlayingTracks,
        zoomLevel,
        maxZoom,
        followPlayhead,

        // Methods
        mount,
        unmount,
        playLiveset,
        play,
        pause,
        togglePlayPause,
        seek,
        setQuality,
        toggleGeneratePeaks,
        getVisiblePosition,
        setZoom,
        setFollowPlayhead,
        initPlayer,
    };
}
