<script setup lang="ts">
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Button, type ButtonVariants } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ListPlus, Plus, Check, Loader2 } from 'lucide-vue-next';
import { useAuth } from '@/composables/useAuth';

interface PlaylistSummary {
    id: number;
    name: string;
    items_count: number;
}

const props = defineProps<{
    livesetId: number;
    buttonType?: ButtonVariants['variant'];
}>();

const { isAuthenticated } = useAuth();

const open = ref(false);
const playlists = ref<PlaylistSummary[]>([]);
const loading = ref(false);
const adding = ref<number | null>(null);
const justAdded = ref<number | null>(null);

function getCsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find(row => row.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

async function loadPlaylists() {
    if (!isAuthenticated.value) return;

    loading.value = true;
    try {
        const response = await fetch('/api/playlists', {
            credentials: 'include',
        });
        if (response.ok) {
            const data = await response.json();
            playlists.value = data.playlists || [];
        }
    } catch (error) {
        console.error('Failed to load playlists:', error);
    } finally {
        loading.value = false;
    }
}

async function addToPlaylist(playlistId: number) {
    adding.value = playlistId;
    try {
        const response = await fetch(`/api/playlists/${playlistId}/items`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
            body: JSON.stringify({
                liveset_id: props.livesetId,
            }),
        });

        if (response.ok || response.status === 409) {
            // Success or already in playlist - show check
            justAdded.value = playlistId;
            // Update count locally
            const playlist = playlists.value.find(p => p.id === playlistId);
            if (playlist && response.ok) {
                playlist.items_count++;
            }
            setTimeout(() => {
                justAdded.value = null;
            }, 1500);
        }
    } catch (error) {
        console.error('Failed to add to playlist:', error);
    } finally {
        adding.value = null;
    }
}

function handleTriggerClick() {
    if (!isAuthenticated.value) {
        router.visit(route('auth.login'));
        return;
    }
}

// Load playlists when dropdown opens
watch(open, (isOpen) => {
    if (isOpen && playlists.value.length === 0) {
        loadPlaylists();
    }
});

function openMenu() {
    if (!isAuthenticated.value) {
        router.visit(route('auth.login'));
        return;
    }
    open.value = true;
}

defineExpose({ openMenu });
</script>

<template>
    <DropdownMenu v-model:open="open">
        <DropdownMenuTrigger as-child>
            <Button
                :variant="buttonType ?? 'ghost'"
                size="icon"
                class="h-8 w-8 rounded-full"
                @click.stop="handleTriggerClick"
                title="Add to playlist"
            >
                <ListPlus class="h-4 w-4 text-muted-foreground" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56" v-if="isAuthenticated">
            <DropdownMenuLabel>Add to playlist</DropdownMenuLabel>
            <DropdownMenuSeparator />

            <div v-if="loading" class="flex items-center justify-center py-4">
                <Loader2 class="h-4 w-4 animate-spin text-muted-foreground" />
            </div>

            <template v-else-if="playlists.length === 0">
                <div class="px-2 py-4 text-sm text-center text-muted-foreground">
                    No playlists yet
                </div>
                <DropdownMenuSeparator />
                <DropdownMenuItem as-child>
                    <Link :href="route('user.playlists')" class="flex items-center">
                        <Plus class="h-4 w-4 mr-2" />
                        Create playlist
                    </Link>
                </DropdownMenuItem>
            </template>

            <template v-else>
                <DropdownMenuItem
                    v-for="playlist in playlists"
                    :key="playlist.id"
                    @click.stop="addToPlaylist(playlist.id)"
                    :disabled="adding === playlist.id"
                    class="flex items-center justify-between cursor-pointer"
                >
                    <span class="truncate">{{ playlist.name }}</span>
                    <span class="flex items-center text-muted-foreground">
                        <Loader2 v-if="adding === playlist.id" class="h-3 w-3 animate-spin" />
                        <Check v-else-if="justAdded === playlist.id" class="h-3 w-3 text-green-500" />
                        <span v-else class="text-xs">{{ playlist.items_count }}</span>
                    </span>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem as-child>
                    <Link :href="route('user.playlists')" class="flex items-center">
                        <Plus class="h-4 w-4 mr-2" />
                        Create playlist
                    </Link>
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
