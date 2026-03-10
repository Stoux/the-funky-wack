<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    ArrowLeft,
    Smartphone,
    Monitor,
    Tablet,
    Car,
    HelpCircle,
    MoreVertical,
    Pencil,
    Eye,
    EyeOff,
    Trash2,
    Check,
    ChevronDown,
    ChevronUp,
    LinkIcon,
} from 'lucide-vue-next';
import UserMenu from '@/components/UserMenu.vue';
import { useAuth } from '@/composables/useAuth';
import { getClientId } from '@/composables/useDeviceId';

interface Device {
    id: number;
    client_id: string;
    device_type: 'mobile' | 'desktop' | 'tablet' | 'car' | 'other';
    device_name: string;
    device_nickname: string | null;
    display_name: string;
    is_hidden: boolean;
    is_stale: boolean;
    last_seen_at: string;
    created_at: string;
}

const { isAuthenticated } = useAuth();

const devices = ref<Device[]>([]);
const loading = ref(true);
const currentClientId = ref<string | null>(null);
const showHiddenDevices = ref(false);

// A device is "excluded" if it's stale or hidden (but not the current device)
function isExcludedDevice(device: Device): boolean {
    if (device.client_id === currentClientId.value) {
        return false; // Always show current device
    }
    return device.is_stale || device.is_hidden;
}

const visibleDevices = computed(() => {
    if (showHiddenDevices.value) {
        return devices.value;
    }
    return devices.value.filter(d => !isExcludedDevice(d));
});

const hiddenDeviceCount = computed(() => {
    return devices.value.filter(d => isExcludedDevice(d)).length;
});

// Rename dialog
const renameDialogOpen = ref(false);
const deviceToRename = ref<Device | null>(null);
const newNickname = ref('');
const renaming = ref(false);

// Delete confirmation
const deleteDialogOpen = ref(false);
const deviceToDelete = ref<Device | null>(null);
const deleting = ref(false);

// Hide/show confirmation
const hideDialogOpen = ref(false);
const deviceToToggleHide = ref<Device | null>(null);

function openHideDialog(device: Device) {
    deviceToToggleHide.value = device;
    hideDialogOpen.value = true;
}

async function confirmToggleHidden() {
    if (!deviceToToggleHide.value) return;
    await toggleHidden(deviceToToggleHide.value);
    hideDialogOpen.value = false;
}

const deviceIcons = {
    mobile: Smartphone,
    desktop: Monitor,
    tablet: Tablet,
    car: Car,
    other: HelpCircle,
};

async function loadDevices() {
    loading.value = true;
    try {
        // Get current client ID for "this device" indicator
        currentClientId.value = await getClientId();

        const response = await fetch('/api/devices', {
            credentials: 'include',
        });
        if (response.ok) {
            const data = await response.json();
            devices.value = data.devices || [];
        }
    } catch (error) {
        console.error('Failed to load devices:', error);
    } finally {
        loading.value = false;
    }
}

function isCurrentDevice(device: Device): boolean {
    return device.client_id === currentClientId.value;
}

function openRenameDialog(device: Device) {
    deviceToRename.value = device;
    newNickname.value = device.device_nickname || '';
    renameDialogOpen.value = true;
}

async function renameDevice() {
    if (!deviceToRename.value) return;

    renaming.value = true;
    try {
        const response = await fetch(`/api/devices/${deviceToRename.value.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
            body: JSON.stringify({
                device_nickname: newNickname.value.trim() || null,
            }),
        });

        if (response.ok) {
            const data = await response.json();
            const device = devices.value.find(d => d.id === deviceToRename.value?.id);
            if (device) {
                device.device_nickname = data.device.device_nickname;
                device.display_name = data.device.display_name;
            }
            renameDialogOpen.value = false;
        }
    } catch (error) {
        console.error('Failed to rename device:', error);
    } finally {
        renaming.value = false;
    }
}

async function toggleHidden(device: Device) {
    const endpoint = device.is_hidden ? 'show' : 'hide';
    try {
        const response = await fetch(`/api/devices/${device.id}/${endpoint}`, {
            method: 'PUT',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
        });

        if (response.ok) {
            device.is_hidden = !device.is_hidden;
        }
    } catch (error) {
        console.error('Failed to toggle device visibility:', error);
    }
}

function openDeleteDialog(device: Device) {
    deviceToDelete.value = device;
    deleteDialogOpen.value = true;
}

async function deleteDevice() {
    if (!deviceToDelete.value) return;

    deleting.value = true;
    try {
        const response = await fetch(`/api/devices/${deviceToDelete.value.id}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
        });

        if (response.ok) {
            devices.value = devices.value.filter(d => d.id !== deviceToDelete.value?.id);
            deleteDialogOpen.value = false;
        }
    } catch (error) {
        console.error('Failed to delete device:', error);
    } finally {
        deleting.value = false;
    }
}

function formatDate(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} min ago`;
    if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;

    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function getCsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find(row => row.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

onMounted(() => {
    if (!isAuthenticated.value) {
        router.visit(route('auth.login'));
        return;
    }
    loadDevices();
});
</script>

<template>
    <Head title="Devices" />

    <div class="p-4">
        <div class="max-w-4xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('home')">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <h1 class="text-2xl font-bold">Your Devices</h1>
                </div>
                <UserMenu />
            </div>

            <div class="flex items-center justify-between">
                <p class="text-muted-foreground">
                    Manage the devices connected to your account. Hidden devices won't appear in "Continue listening" suggestions.
                </p>
                <Link :href="route('user.link-device')">
                    <Button variant="outline" size="sm" class="shrink-0">
                        <LinkIcon class="h-4 w-4 mr-2" />
                        Link a Device
                    </Button>
                </Link>
            </div>

            <div v-if="loading" class="text-center py-8">
                <p class="text-muted-foreground">Loading...</p>
            </div>

            <div v-else-if="devices.length === 0" class="text-center py-8 border rounded-lg">
                <Monitor class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                <p class="text-muted-foreground">No devices found.</p>
                <p class="text-sm text-muted-foreground mt-2">
                    Devices will appear here once you start using the app.
                </p>
            </div>

            <div v-else class="space-y-2">
                <div
                    v-for="device in visibleDevices"
                    :key="device.id"
                    class="flex items-center justify-between p-4 rounded-lg border bg-card"
                    :class="{ 'opacity-50': device.is_hidden }"
                >
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            <component
                                :is="deviceIcons[device.device_type] || deviceIcons.other"
                                class="h-6 w-6 text-muted-foreground"
                            />
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <p class="font-medium">{{ device.display_name }}</p>
                                <span
                                    v-if="isCurrentDevice(device)"
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary text-primary-foreground"
                                >
                                    <Check class="h-3 w-3 mr-1" />
                                    This device
                                </span>
                                <span
                                    v-if="device.is_hidden"
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-muted text-muted-foreground"
                                >
                                    Hidden
                                </span>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Last active {{ formatDate(device.last_seen_at) }}
                            </p>
                            <p v-if="device.device_nickname" class="text-xs text-muted-foreground">
                                {{ device.device_name }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1">
                        <!-- Quick delete button for hidden/stale devices (not current device) -->
                        <Button
                            v-if="showHiddenDevices && isExcludedDevice(device) && !isCurrentDevice(device)"
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 text-destructive hover:text-destructive"
                            @click="openDeleteDialog(device)"
                            title="Remove device"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>

                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="ghost" size="icon" class="h-8 w-8">
                                    <MoreVertical class="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem @click="openRenameDialog(device)">
                                    <Pencil class="h-4 w-4 mr-2" />
                                    Rename
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="openHideDialog(device)">
                                    <component :is="device.is_hidden ? Eye : EyeOff" class="h-4 w-4 mr-2" />
                                    {{ device.is_hidden ? 'Show' : 'Hide' }}
                                </DropdownMenuItem>
                                <template v-if="!isCurrentDevice(device)">
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        class="text-destructive focus:text-destructive"
                                        @click="openDeleteDialog(device)"
                                    >
                                        <Trash2 class="h-4 w-4 mr-2" />
                                        Remove
                                    </DropdownMenuItem>
                                </template>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                <!-- Show/hide old/hidden devices toggle -->
                <button
                    v-if="hiddenDeviceCount > 0"
                    @click="showHiddenDevices = !showHiddenDevices"
                    class="flex items-center justify-center w-full py-2 text-sm text-muted-foreground hover:text-foreground transition-colors"
                >
                    <template v-if="showHiddenDevices">
                        <ChevronUp class="h-4 w-4 mr-1" />
                        Hide {{ hiddenDeviceCount }} old/hidden {{ hiddenDeviceCount === 1 ? 'device' : 'devices' }}
                    </template>
                    <template v-else>
                        <ChevronDown class="h-4 w-4 mr-1" />
                        Show {{ hiddenDeviceCount }} old/hidden {{ hiddenDeviceCount === 1 ? 'device' : 'devices' }}
                    </template>
                </button>
            </div>
        </div>
    </div>

    <!-- Rename Dialog -->
    <Dialog v-model:open="renameDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Rename Device</DialogTitle>
                <DialogDescription>
                    Give this device a custom name to easily identify it.
                </DialogDescription>
            </DialogHeader>
            <div class="py-4">
                <Input
                    v-model="newNickname"
                    :placeholder="deviceToRename?.device_name"
                    @keyup.enter="renameDevice"
                />
                <p class="text-xs text-muted-foreground mt-2">
                    Leave empty to use the default name: {{ deviceToRename?.device_name }}
                </p>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="renameDialogOpen = false">
                    Cancel
                </Button>
                <Button @click="renameDevice" :disabled="renaming">
                    {{ renaming ? 'Saving...' : 'Save' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Delete Confirmation Dialog -->
    <Dialog v-model:open="deleteDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Remove Device</DialogTitle>
                <DialogDescription>
                    Are you sure you want to remove "{{ deviceToDelete?.display_name }}"?
                    This will clear its playback history association.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleteDialogOpen = false">
                    Cancel
                </Button>
                <Button variant="destructive" @click="deleteDevice" :disabled="deleting">
                    {{ deleting ? 'Removing...' : 'Remove' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Hide/Show Confirmation Dialog -->
    <Dialog v-model:open="hideDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{ deviceToToggleHide?.is_hidden ? 'Show Device' : 'Hide Device' }}
                </DialogTitle>
                <DialogDescription>
                    <template v-if="deviceToToggleHide?.is_hidden">
                        Showing "{{ deviceToToggleHide?.display_name }}" will include it in
                        "Continue listening" suggestions again.
                    </template>
                    <template v-else>
                        Hiding "{{ deviceToToggleHide?.display_name }}" will exclude it from
                        "Continue listening" suggestions. The device will still track playback
                        and appear in your history.
                    </template>
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="hideDialogOpen = false">
                    Cancel
                </Button>
                <Button @click="confirmToggleHidden">
                    {{ deviceToToggleHide?.is_hidden ? 'Show Device' : 'Hide Device' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
