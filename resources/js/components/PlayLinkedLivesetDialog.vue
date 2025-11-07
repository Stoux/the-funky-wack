<script setup lang="ts">
import {onMounted, ref} from 'vue';
import {Edition, Liveset} from '@/types';
import {useNowPlayingState} from '@/composables/useNowPlayingState';
import {formatDuration, parseDuration} from '@/lib/utils';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

const props = defineProps<{
    editions: Edition[],
}>();

const open = ref(false);
const targetLiveset = ref<Liveset | undefined>();
const targetEdition = ref<Edition | undefined>();
const atTime = ref<number>(0);

const {currentEdition, currentLiveset, currentTime, playing} = useNowPlayingState();

function maybeOpenFromHash() {
    const hash = window.location.hash ?? '';
    if (!hash || hash.length <= 1) return;

    // Expect format: #lid=123&t=01:23 (t optional)
    const params = new URLSearchParams(hash.replace(/^#/, ''));
    const lidParam = params.get('liveset');

    // Validate lid is an integer
    if (!lidParam || !/^\d+$/.test(lidParam)) {
        return
    }

    // Validate and parse time parameter if present
    let seconds = 0;
    const timeParam = params.get('t');
    if (timeParam && /^\d{2}:\d{2}(:\d{2})?$/.test(timeParam)) {
        seconds = timeParam ? (parseDuration(timeParam) ?? 0) : 0;
    }

    // Find liveset by id across editions
    const id = Number(lidParam);

    for (const foundEdition of props.editions) {
        const foundLiveset = foundEdition.livesets?.find(l => l.id === id);
        if (!foundLiveset) {
            continue
        }

        // Only open if at least one playable file is present
        const files = foundLiveset.files;
        if (!files || !(files.lq || files.hq || files.lossless)) {
            return;
        }

        // Store the liveset and edition & prompt the user
        targetEdition.value = foundEdition;
        targetLiveset.value = foundLiveset;
        atTime.value = seconds;
        open.value = true;
        return;
    }
}

// Remove only our deep-link hash params (lid/liveset and t), preserve the rest of the hash
function clearDeepLinkParams() {
    const raw = window.location.hash ?? '';
    if (!raw || raw.length <= 1) {
        return;
    }
    const withoutHash = raw.replace(/^#/, '');
    const parts = withoutHash.split('&').filter(Boolean);
    const kept = parts.filter(p => {
        const lower = p.toLowerCase();
        return !(lower.startsWith('lid=') || lower.startsWith('liveset=') || lower.startsWith('t='));
    });

    const newHash = kept.length ? `#${kept.join('&')}` : '';
    const newUrl = window.location.pathname + window.location.search + newHash;
    history.replaceState(null, '', newUrl);
}

function confirmPlay() {
    if (!targetEdition.value || !targetLiveset.value) {
        return
    }

    currentEdition.value = targetEdition.value;
    currentLiveset.value = targetLiveset.value;
    currentTime.value = atTime.value ?? 0;
    playing.value = true;

    // Clear only our deep-link params so refreshing won't re-open
    clearDeepLinkParams();

    // Close dialog
    open.value = false;
}

function cancelDialog() {
    // User opted not to play now; clear only our deep-link params to avoid re-prompting on refresh
    clearDeepLinkParams();
    open.value = false;
}


onMounted(() => {
    maybeOpenFromHash();
});
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogContent>
            <AlertDialogHeader v-if="targetLiveset && targetEdition">
                <AlertDialogTitle>Play "{{ targetLiveset.title }}"{{
                        atTime ? ` @ ${formatDuration(atTime)}?` : '?'
                    }}
                </AlertDialogTitle>
                <AlertDialogDescription>
                    From TFW #{{ targetEdition.number }}<span v-if="targetEdition.tag_line">: {{
                        targetEdition.tag_line
                    }}</span>
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="cancelDialog">Not now</AlertDialogCancel>
                <AlertDialogAction @click="confirmPlay">Play</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>

<style scoped>

</style>
