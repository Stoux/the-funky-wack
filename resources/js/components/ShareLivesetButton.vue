<script setup lang="ts">
import {ref, computed, watch} from 'vue';
import {Edition, Liveset} from '@/types';
import {Button, type ButtonVariants} from '@/components/ui/button';
import {Copy, Share2, Check} from 'lucide-vue-next';
import {
    Dialog,
    DialogTrigger,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
    DialogClose,
} from '@/components/ui/dialog';
import {useNowPlayingState} from '@/composables/useNowPlayingState';
import {formatDuration} from '@/lib/utils';

const props = defineProps<{
    edition: Edition,
    liveset: Liveset,
    buttonType?: ButtonVariants['variant'],
}>();

const open = ref(false);

// Whether to include the time in the URL; defaults to true
const includeTime = ref(false);
const isDirty = ref(false); // stops auto-updating when true
const timeString = ref<string>('00:00');

// Follow the now playing time until the user focuses/edits
const {currentLiveset, currentTime} = useNowPlayingState();

// Update the time in the URL
watch(currentTime, (t) => {
    if (!open.value) return;
    if (isDirty.value) return;
    if (currentLiveset.value?.id !== props.liveset.id) return;
    const seconds = Math.max(0, Math.floor(t));

    timeString.value = formatDuration(seconds, undefined, '00:00');
});

// When dialog opens, initialize the fields
watch(open, (isOpen) => {
    if (isOpen) {
        // Only include the time by default, when that liveset is currently playing.
        const isCurrentLiveset = currentLiveset.value?.id === props.liveset.id;
        includeTime.value = isCurrentLiveset;
        isDirty.value = false;

        // Seed with current time of this liveset (or 0)
        const seconds = isCurrentLiveset ? Math.floor(currentTime.value) : 0;
        timeString.value = formatDuration(seconds, undefined, '00:00');
    } else {
        // Reset flags when closing
        isDirty.value = false;
        // Also reset copy feedback
        copied.value = false;
    }
});

// Build the shareable URL
const shareUrl = computed(() => {
    const base = window.location.origin + window.location.pathname + window.location.search;
    const lid = `liveset=${props.liveset.id}`;

    // Decide whether to include t parameter
    if (!includeTime.value) {
        return `${base}#${lid}`;
    }

    // Use the free-form time string directly; opener will validate/ignore invalid
    const t = (timeString.value ?? '').trim();
    return `${base}#${lid}&t=${t}`;
});

// Copy handling
const copied = ref(false);

async function copyToClipboard() {
    try {
        await navigator.clipboard.writeText(shareUrl.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    } catch (e) {
        // Fallback: select text? We keep it minimal
        console.error('Copy failed', e);
    }
}

function onTimeFocus() {
    // As soon as user focuses, stop auto-updating
    isDirty.value = true;
}

function onTimeInput() {
    // User edited the time; ensure we stop auto-updating
    isDirty.value = true;
}

// Lenient time parsing used only on blur:
// Supports examples: "12" => 12s, "0112" => 1m12s, "1:2" => 1m2s, "01:02:03" => 1h2m3s
function parseLenientTime(input: string): number | undefined {
    if (!input) return undefined;
    const raw = input.trim();

    // If only digits, map from right to left (..HH)(MM)(SS)
    if (/^\d+$/.test(raw)) {
        const s = raw;
        const len = s.length;
        let ss = 0, mm = 0, hh = 0;
        if (len <= 2) {
            ss = Number(s);
        } else if (len <= 4) {
            ss = Number(s.slice(len - 2));
            mm = Number(s.slice(0, len - 2));
        } else {
            ss = Number(s.slice(len - 2));
            mm = Number(s.slice(len - 4, len - 2));
            hh = Number(s.slice(0, len - 4));
        }
        if (Number.isNaN(ss) || Number.isNaN(mm) || Number.isNaN(hh)) return undefined;
        return hh * 3600 + mm * 60 + ss;
    }

    // Allow colon-separated parts, lenient digits per part
    if (/^[\d:\s]+$/.test(raw)) {
        const parts = raw.split(':').map(p => p.trim()).filter(p => p.length > 0);
        if (parts.length === 1) {
            const n = Number(parts[0]);
            return Number.isNaN(n) ? undefined : n;
        }
        if (parts.length === 2) {
            const mm = Number(parts[0]);
            const ss = Number(parts[1]);
            if (Number.isNaN(mm) || Number.isNaN(ss)) return undefined;
            return mm * 60 + ss;
        }
        if (parts.length >= 3) {
            const hh = Number(parts[0]);
            const mm = Number(parts[1]);
            const ss = Number(parts[2]);
            if (Number.isNaN(hh) || Number.isNaN(mm) || Number.isNaN(ss)) return undefined;
            return hh * 3600 + mm * 60 + ss;
        }
    }

    return undefined;
}

function onTimeBlur() {
    // Only validate/normalize when leaving the field; be lenient
    const seconds = parseLenientTime(timeString.value ?? '');
    if (seconds !== undefined) {
        timeString.value = formatDuration(Math.max(0, Math.floor(seconds)), undefined, '00:00');
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button size="icon" :variant="buttonType ?? 'outline'" class="h-8 w-8 rounded-full"
                    title="Share this liveset">
                <Share2 class="h-4 w-4"/>
            </Button>
        </DialogTrigger>

        <DialogContent>
            <DialogHeader>
                <DialogTitle>Share liveset</DialogTitle>
                <DialogDescription>
                    {{ props.liveset.title }} • TFW #{{ props.edition.number }}
                </DialogDescription>
            </DialogHeader>

            <div class="flex flex-col gap-3 p-1">
                <!-- URL row -->
                <div class="flex items-center gap-2">
                    <input :value="shareUrl" disabled
                           class="flex-1 rounded-md border bg-muted p-2 text-sm overflow-hidden text-ellipsis">
                    <Button
                        variant="outline"
                        size="icon"
                        class="h-8 w-8"
                        :class="copied ? 'text-green-600 border-green-500' : ''"
                        @click="copyToClipboard"
                        :title="copied ? 'Copied!' : 'Copy to clipboard'"
                    >
                        <Check v-if="copied" class="h-4 w-4"/>
                        <Copy v-else class="h-4 w-4"/>
                    </Button>
                </div>

                <!-- Time row -->
                <div class="flex items-center gap-2 text-sm">
                    <label class="inline-flex items-center gap-2 select-none">
                        <input type="checkbox" v-model="includeTime" class="accent-primary">
                        <span>at</span>
                    </label>
                    <input
                        type="text"
                        step="1"
                        :disabled="!includeTime"
                        class="rounded-md border p-2 text-sm disabled:opacity-50"
                        :value="timeString"
                        @focus="onTimeFocus"
                        @input="onTimeInput; (timeString as any) = ($event.target as HTMLInputElement).value"
                        @blur="onTimeBlur"
                    >
                    <span class="text-muted-foreground text-xs">
                        (hh:mm:ss)
                    </span>
                </div>
            </div>

            <DialogFooter>
                <DialogClose as-child>
                    <Button>Close</Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
</style>
