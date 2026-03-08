import { ref, computed, watch, nextTick, shallowRef } from 'vue';
import WaveSurfer from 'wavesurfer.js';
import HoverPlugin from 'wavesurfer.js/plugins/hover';
import { Edition, Liveset, LivesetFilesByQuality, LivesetQuality } from '@/types';
import { useNowPlayingState } from './useNowPlayingState';
import { usePlaybackSync } from './usePlaybackSync';
import { useTracklistNowPlaying } from './useTracklistNowPlaying';
import { useCastMedia } from './useCastMedia';
import { formatDuration } from '@/lib/utils';
import { determineNowPlayingTrack } from '@/lib/tracklist.utils';

// Module-level state (survives navigation)
let waveInstance: WaveSurfer | undefined = undefined;
let audioElement: HTMLAudioElement | undefined = undefined;
let waveformContainer: HTMLElement | null = null;

const isInitialized = ref(false);
const loadingSource = ref<string | undefined>(undefined);
const hasPeaks = ref<boolean | undefined>(undefined);
const generatePeaksIfMissing = ref(false);

// Track if this is the initial quality setup (vs user changing quality)
let isInitialQuality = true;
let lastQuality: string | undefined = undefined;

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

        waveInstance.play(shouldBeAtTime)?.catch((e: Error) => {
            if (e.name !== 'AbortError') throw e;
        });
    });

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
    async function initPlayer() {
        if (!waveformContainer || !audioElement) {
            console.warn('[AudioPlayer] Cannot init player - DOM elements not available');
            return;
        }

        // Check if not already loading this source
        if (source.value && source.value === loadingSource.value) {
            return;
        }

        // End current playback session FIRST
        await endPlayback();

        // Destroy the old instance
        waveInstance?.destroy();
        waveInstance = undefined;

        if (!source.value) {
            console.log('[AudioPlayer] No source');
            return;
        }

        playing.value = false;
        finished.value = false;
        loading.value = true;
        loadingSource.value = source.value;

        // Handle remote playback (Chromecast)
        if (castMedia.casting.value && audioElement) {
            castMedia.casting.value = 'reconnecting';
            audioElement.disableRemotePlayback = true;
        }

        // Load peaks if available
        hasPeaks.value = undefined;
        let peaks: number[][] | undefined = generatePeaksIfMissing.value ? undefined : [[]];
        if (currentLiveset.value?.audio_waveform_url) {
            const peaksForSource = source.value;
            try {
                const peakData: { data: number[][] } = await fetch(currentLiveset.value.audio_waveform_url).then(response => response.json());
                peaks = peakData.data;
                hasPeaks.value = true;
            } catch (e) {
                console.log('[AudioPlayer] Failed to load waveform peaks', e);
            }

            if (peaksForSource !== source.value) {
                // Source changed! Abort loading
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
            autoCenter: false,
            minPxPerSec: 1,
            peaks: peaks,
            url: source.value,
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

                        lastShownTrackIndex = determineNowPlayingTrack(
                            nowPlayingSections.value,
                            seconds,
                            lastShownTrackIndex,
                        );

                        const duration = formatDuration(seconds);
                        if (lastShownTrackIndex === undefined) {
                            return duration;
                        }

                        const track = nowPlayingSections.value[lastShownTrackIndex];
                        return `${duration} | ${track.title}`;
                    },
                })
            ]
        });

        surfer.on('click', () => {
            surfer.play();
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

            // Play at the configured time
            surfer.play(currentTime.value);

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
            waveInstance.destroy();
            waveInstance = undefined;
        }
        waveformContainer = null;
        isInitialized.value = false;
        // Note: audioElement is kept alive at module level, audio continues
    }

    /**
     * Play a liveset
     */
    function playLiveset(edition: Edition, liveset: Liveset, qualityOverride?: LivesetQuality, atTime: number = 0) {
        // If same liveset, just toggle play/pause
        if (currentEdition.value?.id === edition.id &&
            currentLiveset.value?.id === liveset.id &&
            (qualityOverride === undefined || qualityOverride === quality.value)) {
            playing.value = !playing.value;
            return;
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
     * Seek to a specific time
     */
    function seek(time: number) {
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
            initPlayer();
        }
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
        initPlayer,
    };
}
