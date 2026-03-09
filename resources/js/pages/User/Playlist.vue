<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAudioPlayer } from '@/composables/useAudioPlayer';
import { useEditions } from '@/composables/useEditions';
import { useQueue } from '@/composables/useQueue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { ArrowLeft, Play, Trash2, Share2, Copy, Check, Lock, Globe, Link as LinkIcon, Pencil, RefreshCw, User } from 'lucide-vue-next';
import UserMenu from '@/components/UserMenu.vue';
import { formatDuration } from '@/lib/utils';

const { playLiveset } = useAudioPlayer();
const { findLivesetById } = useEditions();
const { setQueue } = useQueue();

interface PlaylistItem {
    id: number;
    liveset_id: number;
    position: number;
    liveset: {
        id: number;
        title: string;
        artist_name: string;
        duration_in_seconds: number | null;
        edition: { id: number; number: string } | null;
    } | null;
}

interface Playlist {
    id: number;
    name: string;
    description: string | null;
    visibility: 'private' | 'public' | 'unlisted';
    share_code: string;
    slug: string;
    is_owner: boolean;
    user: { id: number; name: string } | null;
    items: PlaylistItem[];
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    shareCode: string;
    isOwner: boolean;
}>();

const playlist = ref<Playlist | null>(null);
const loading = ref(true);
const editDialogOpen = ref(false);
const shareDialogOpen = ref(false);
const regenerateDialogOpen = ref(false);
const editName = ref('');
const editDescription = ref('');
const editVisibility = ref<'private' | 'public' | 'unlisted'>('private');
const saving = ref(false);
const regenerating = ref(false);
const copiedShareLink = ref(false);

const shareUrl = computed(() => {
    if (!playlist.value) return '';
    return `${window.location.origin}/playlists/${playlist.value.share_code}/${playlist.value.slug}`;
});

const isOwner = computed(() => playlist.value?.is_owner ?? props.isOwner);

async function loadPlaylist() {
    loading.value = true;
    try {
        const response = await fetch(`/api/playlists/${props.shareCode}`, {
            credentials: 'include',
        });
        if (response.ok) {
            const data = await response.json();
            playlist.value = data.playlist;
        } else if (response.status === 403 || response.status === 404) {
            router.visit(route('user.playlists'));
        }
    } catch (error) {
        console.error('Failed to load playlist:', error);
    } finally {
        loading.value = false;
    }
}

function openEditDialog() {
    if (!playlist.value) return;
    editName.value = playlist.value.name;
    editDescription.value = playlist.value.description || '';
    editVisibility.value = playlist.value.visibility;
    editDialogOpen.value = true;
}

async function savePlaylist() {
    if (!playlist.value) return;

    saving.value = true;
    try {
        const response = await fetch(`/api/playlists/${playlist.value.share_code}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
            body: JSON.stringify({
                name: editName.value,
                description: editDescription.value || null,
                visibility: editVisibility.value,
            }),
        });

        if (response.ok) {
            const data = await response.json();
            playlist.value = {
                ...playlist.value!,
                ...data.playlist,
            };
            editDialogOpen.value = false;

            // Redirect to new slug if name changed
            if (data.playlist.slug !== props.shareCode.split('/')[1]) {
                router.visit(route('playlist.show', {
                    shareCode: playlist.value!.share_code,
                    slug: playlist.value!.slug,
                }), { preserveState: true });
            }
        }
    } catch (error) {
        console.error('Failed to save playlist:', error);
    } finally {
        saving.value = false;
    }
}

async function removeItem(itemId: number, livesetId: number) {
    if (!playlist.value) return;

    try {
        const response = await fetch(`/api/playlists/${playlist.value.share_code}/items/${livesetId}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
        });

        if (response.ok) {
            playlist.value.items = playlist.value.items.filter(i => i.id !== itemId);
        }
    } catch (error) {
        console.error('Failed to remove item:', error);
    }
}

async function deletePlaylist() {
    if (!playlist.value || !confirm('Are you sure you want to delete this playlist?')) return;

    try {
        const response = await fetch(`/api/playlists/${playlist.value.share_code}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
        });

        if (response.ok) {
            router.visit(route('user.playlists'));
        }
    } catch (error) {
        console.error('Failed to delete playlist:', error);
    }
}

async function regenerateCode() {
    if (!playlist.value) return;

    regenerating.value = true;
    try {
        const response = await fetch(`/api/playlists/${playlist.value.share_code}/regenerate-code`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
        });

        if (response.ok) {
            const data = await response.json();
            playlist.value.share_code = data.share_code;
            regenerateDialogOpen.value = false;

            // Redirect to new URL
            router.visit(route('playlist.show', {
                shareCode: data.share_code,
                slug: data.slug,
            }), { preserveState: true });
        }
    } catch (error) {
        console.error('Failed to regenerate code:', error);
    } finally {
        regenerating.value = false;
    }
}

async function copyShareLink() {
    try {
        await navigator.clipboard.writeText(shareUrl.value);
        copiedShareLink.value = true;
        setTimeout(() => {
            copiedShareLink.value = false;
        }, 2000);
    } catch (error) {
        console.error('Failed to copy:', error);
    }
}

function getVisibilityIcon(visibility: string) {
    switch (visibility) {
        case 'public': return Globe;
        case 'unlisted': return LinkIcon;
        default: return Lock;
    }
}

function getCsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find(row => row.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

function handlePlay(livesetId: number) {
    const result = findLivesetById(livesetId);
    if (result) {
        // Queue remaining items after the one being played
        if (playlist.value) {
            const itemIndex = playlist.value.items.findIndex(i => i.liveset_id === livesetId);
            if (itemIndex >= 0) {
                const remaining = playlist.value.items.slice(itemIndex + 1);
                setQueue(
                    remaining.map(item => item.liveset_id),
                    { type: 'playlist', shareCode: props.shareCode }
                );
            }
        }

        playLiveset(result.edition, result.liveset);
    }
}

onMounted(() => {
    loadPlaylist();
});
</script>

<template>
    <Head :title="playlist?.name || 'Playlist'" />

    <div class="min-h-screen p-4">
        <div class="max-w-4xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('user.playlists')">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div v-if="playlist">
                        <div class="flex items-center space-x-2">
                            <h1 class="text-2xl font-bold">{{ playlist.name }}</h1>
                            <component :is="getVisibilityIcon(playlist.visibility)" class="h-4 w-4 text-muted-foreground" />
                        </div>
                        <p v-if="playlist.description" class="text-sm text-muted-foreground">
                            {{ playlist.description }}
                        </p>
                        <p v-if="!isOwner && playlist.user" class="text-sm text-muted-foreground flex items-center gap-1">
                            <User class="h-3 w-3" />
                            {{ playlist.user.name }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <Button v-if="playlist && isOwner" variant="outline" size="icon" @click="openEditDialog">
                        <Pencil class="h-4 w-4" />
                    </Button>
                    <Button v-if="playlist && playlist.visibility !== 'private'" variant="outline" size="icon" @click="shareDialogOpen = true">
                        <Share2 class="h-4 w-4" />
                    </Button>
                    <UserMenu />
                </div>
            </div>

            <div v-if="loading" class="text-center py-8">
                <p class="text-muted-foreground">Loading...</p>
            </div>

            <div v-else-if="playlist && playlist.items.length === 0" class="text-center py-8">
                <p class="text-muted-foreground">This playlist is empty.</p>
                <p v-if="isOwner" class="text-sm text-muted-foreground mt-2">
                    Add livesets from the home page!
                </p>
            </div>

            <div v-else-if="playlist" class="space-y-2">
                <div
                    v-for="item in playlist.items"
                    :key="item.id"
                    class="flex items-center justify-between p-4 rounded-lg border bg-card"
                >
                    <div class="flex items-center space-x-4">
                        <Button size="icon" variant="ghost" class="h-8 w-8 rounded-full" @click="handlePlay(item.liveset_id)">
                            <Play class="h-4 w-4" />
                        </Button>
                        <div>
                            <p class="font-medium">{{ item.liveset?.title || 'Unknown' }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ item.liveset?.artist_name || 'Unknown' }}
                                <span v-if="item.liveset?.edition" class="text-xs">
                                    &bull; TFW #{{ item.liveset.edition.number }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-muted-foreground">
                            {{ item.liveset?.duration_in_seconds ? formatDuration(item.liveset.duration_in_seconds) : '' }}
                        </span>
                        <Button v-if="isOwner" variant="ghost" size="icon" class="h-8 w-8" @click="removeItem(item.id, item.liveset_id)">
                            <Trash2 class="h-4 w-4 text-muted-foreground hover:text-destructive" />
                        </Button>
                    </div>
                </div>
            </div>

            <div v-if="playlist && isOwner" class="pt-4 border-t">
                <Button variant="destructive" @click="deletePlaylist">
                    <Trash2 class="h-4 w-4 mr-2" />
                    Delete Playlist
                </Button>
            </div>
        </div>
    </div>

    <!-- Edit Dialog -->
    <Dialog v-model:open="editDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Edit Playlist</DialogTitle>
                <DialogDescription>
                    Update your playlist details.
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-4 py-4">
                <div class="space-y-2">
                    <Label for="edit-name">Name</Label>
                    <Input id="edit-name" v-model="editName" />
                </div>
                <div class="space-y-2">
                    <Label for="edit-description">Description</Label>
                    <Input id="edit-description" v-model="editDescription" placeholder="Optional description" />
                </div>
                <div class="space-y-2">
                    <Label for="edit-visibility">Visibility</Label>
                    <Select v-model="editVisibility">
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="private">Private - Only you</SelectItem>
                            <SelectItem value="unlisted">Unlisted - Anyone with link</SelectItem>
                            <SelectItem value="public">Public - Everyone</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="editDialogOpen = false">Cancel</Button>
                <Button @click="savePlaylist" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Save' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Share Dialog -->
    <Dialog v-model:open="shareDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Share Playlist</DialogTitle>
                <DialogDescription>
                    Share this playlist with others.
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-4 py-4">
                <div class="flex items-center space-x-2">
                    <Input :value="shareUrl" readonly class="font-mono text-sm" />
                    <Button variant="outline" size="icon" @click="copyShareLink">
                        <Check v-if="copiedShareLink" class="h-4 w-4 text-green-500" />
                        <Copy v-else class="h-4 w-4" />
                    </Button>
                </div>

                <div v-if="isOwner && playlist?.visibility === 'unlisted'" class="pt-4 border-t">
                    <Button variant="outline" class="w-full" @click="regenerateDialogOpen = true">
                        <RefreshCw class="h-4 w-4 mr-2" />
                        Regenerate Link
                    </Button>
                </div>
            </div>
            <DialogFooter>
                <Button @click="shareDialogOpen = false">Done</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Regenerate Link Warning Dialog -->
    <AlertDialog v-model:open="regenerateDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Regenerate Link?</AlertDialogTitle>
                <AlertDialogDescription>
                    This will create a new unique URL for this playlist. All previous links will stop working and anyone who has the old link will no longer be able to access the playlist.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction @click="regenerateCode" :disabled="regenerating">
                    {{ regenerating ? 'Regenerating...' : 'Regenerate' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
