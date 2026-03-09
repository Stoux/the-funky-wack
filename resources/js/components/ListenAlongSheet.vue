<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
    SheetFooter,
    SheetClose,
} from '@/components/ui/sheet';
import { Play, Link2, Unlink } from 'lucide-vue-next';
import { useListenAlong, type LiveSession } from '@/composables/useListenAlong';
import { useAudioPlayer } from '@/composables/useAudioPlayer';
import { useEditions } from '@/composables/useEditions';

const props = defineProps<{
    session: LiveSession;
}>();

const { joinRoom } = useListenAlong();
const { playLiveset } = useAudioPlayer();
const { findLivesetById } = useEditions();

const joining = ref(false);
const open = ref(false);

function estimatedPosition(): number {
    const base = props.session.position;
    const updatedAt = props.session.position_updated_at ? new Date(props.session.position_updated_at).getTime() : 0;
    if (!updatedAt) return base;

    const elapsed = Math.max(0, (Date.now() - updatedAt) / 1000);
    const duration = props.session.liveset?.duration_in_seconds ?? Infinity;
    return Math.floor(Math.min(base + elapsed, duration));
}

async function handleJoin(mode: 'synced' | 'independent') {
    if (!props.session.liveset) return;

    joining.value = true;

    try {
        const success = await joinRoom(props.session.channel_token, mode);

        if (success && props.session.liveset) {
            // Fetch fresh state to get accurate position
            let position = estimatedPosition();
            try {
                const stateRes = await fetch(`/api/live/rooms/${props.session.channel_token}/state`, {
                    credentials: 'include',
                });
                if (stateRes.ok) {
                    const state = await stateRes.json();
                    const updatedAt = state.position_updated_at ? new Date(state.position_updated_at).getTime() : 0;
                    const elapsed = updatedAt ? Math.max(0, (Date.now() - updatedAt) / 1000) : 0;
                    position = Math.floor(state.position + elapsed);
                }
            } catch { /* fall back to estimated position */ }

            const result = findLivesetById(props.session.liveset.id);
            if (result) {
                playLiveset(
                    result.edition,
                    result.liveset,
                    props.session.quality as any,
                    position
                );
            }
        }

        open.value = false;
    } finally {
        joining.value = false;
    }
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetTrigger as-child>
            <Button size="sm" variant="outline">
                <Play class="h-4 w-4 mr-2" />
                Listen Along
            </Button>
        </SheetTrigger>
        <SheetContent side="bottom" class="max-w-lg mx-auto rounded-t-lg">
            <SheetHeader>
                <SheetTitle>Listen Along with {{ session.host.name }}</SheetTitle>
                <SheetDescription v-if="session.liveset">
                    Currently playing: {{ session.liveset.title }} by {{ session.liveset.artist_name }}
                </SheetDescription>
            </SheetHeader>

            <div class="space-y-3 py-4">
                <button
                    class="w-full text-left p-4 rounded-lg border hover:bg-muted/50 transition-colors"
                    :disabled="joining"
                    @click="handleJoin('synced')"
                >
                    <div class="flex items-center space-x-3">
                        <Link2 class="h-5 w-5 text-primary shrink-0" />
                        <div>
                            <p class="font-medium">Synced</p>
                            <p class="text-sm text-muted-foreground">
                                Mirror their playback. You'll follow their seeks, track switches, and pauses.
                            </p>
                        </div>
                    </div>
                </button>

                <button
                    class="w-full text-left p-4 rounded-lg border hover:bg-muted/50 transition-colors"
                    :disabled="joining"
                    @click="handleJoin('independent')"
                >
                    <div class="flex items-center space-x-3">
                        <Unlink class="h-5 w-5 text-muted-foreground shrink-0" />
                        <div>
                            <p class="font-medium">Independent</p>
                            <p class="text-sm text-muted-foreground">
                                Start from their current point but play on your own. No syncing.
                            </p>
                        </div>
                    </div>
                </button>
            </div>

            <SheetFooter>
                <SheetClose as-child>
                    <Button variant="ghost">Cancel</Button>
                </SheetClose>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
