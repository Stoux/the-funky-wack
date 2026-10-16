<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Check, KeyRound, Pencil, Trash2, X } from 'lucide-vue-next';
import { Passkeys, PasskeyExistsError, UserCancelledError } from '@laravel/passkeys';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import ConfirmDialog from '@/components/ConfirmDialog.vue';

export interface PasskeyRow {
    id: number;
    name: string;
    authenticator: string | null;
    last_used_at: string | null;
    created_at: string;
}

defineProps<{
    passkeys: PasskeyRow[];
}>();

const supported = Passkeys.isSupported();
const name = ref('');
const busy = ref(false);
const error = ref<string | null>(null);

function getCsrfToken(): string {
    const cookie = document.cookie.split('; ').find(row => row.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

function reloadPasskeys() {
    router.reload({ only: ['passkeys'] });
}

async function addPasskey() {
    busy.value = true;
    error.value = null;
    try {
        await Passkeys.register({ name: name.value.trim() || 'My passkey' });
        name.value = '';
        reloadPasskeys();
    } catch (e) {
        if (e instanceof PasskeyExistsError) {
            error.value = 'This device already has a passkey for your account.';
        } else if (!(e instanceof UserCancelledError)) {
            error.value = 'Adding the passkey failed, try again.';
        }
    } finally {
        busy.value = false;
    }
}

async function removePasskey(passkey: PasskeyRow) {
    const response = await fetch(`/user/passkeys/${passkey.id}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-XSRF-TOKEN': getCsrfToken() },
        credentials: 'include',
    });
    if (response.ok) {
        reloadPasskeys();
    } else {
        error.value = 'Removing the passkey failed, try again.';
    }
}

// Renaming, inline
const editingId = ref<number | null>(null);
const editName = ref('');

function startRename(passkey: PasskeyRow) {
    editingId.value = passkey.id;
    editName.value = passkey.name;
    error.value = null;
}

async function saveRename(passkey: PasskeyRow) {
    const trimmed = editName.value.trim();
    if (!trimmed || trimmed === passkey.name) {
        editingId.value = null;
        return;
    }
    const response = await fetch(`/user/passkeys/${passkey.id}`, {
        method: 'PATCH',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrfToken() },
        credentials: 'include',
        body: JSON.stringify({ name: trimmed }),
    });
    if (response.ok) {
        editingId.value = null;
        reloadPasskeys();
    } else {
        error.value = 'Renaming the passkey failed, try again.';
    }
}

function formatDate(iso: string | null): string {
    return iso ? new Date(iso).toLocaleDateString() : 'never';
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center space-x-2">
                <KeyRound class="h-5 w-5" />
                <span>Passkeys</span>
            </CardTitle>
            <CardDescription>Sign in with your fingerprint, face or device PIN instead of your password</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <div v-if="passkeys.length" class="divide-y rounded-lg border">
                <div v-for="passkey in passkeys" :key="passkey.id" class="flex items-center gap-3 p-3">
                    <div class="min-w-0 flex-1">
                        <form v-if="editingId === passkey.id" class="flex items-center gap-2" @submit.prevent="saveRename(passkey)">
                            <Input
                                v-model="editName"
                                name="passkey_rename"
                                aria-label="Passkey name"
                                maxlength="255"
                                class="h-8"
                                autofocus
                                @keydown.esc="editingId = null"
                            />
                            <Button type="submit" size="icon" variant="ghost" class="h-8 w-8 shrink-0" title="Save">
                                <Check class="h-4 w-4" />
                            </Button>
                            <Button type="button" size="icon" variant="ghost" class="h-8 w-8 shrink-0" title="Cancel" @click="editingId = null">
                                <X class="h-4 w-4" />
                            </Button>
                        </form>
                        <div v-else class="truncate font-medium">
                            {{ passkey.name }}
                            <span v-if="passkey.authenticator" class="text-muted-foreground">· {{ passkey.authenticator }}</span>
                        </div>
                        <div class="text-xs text-muted-foreground">
                            Added {{ formatDate(passkey.created_at) }} · last used {{ formatDate(passkey.last_used_at) }}
                        </div>
                    </div>
                    <Button v-if="editingId !== passkey.id" variant="ghost" size="icon" class="h-8 w-8" :title="`Rename ${passkey.name}`" @click="startRename(passkey)">
                        <Pencil class="h-4 w-4" />
                    </Button>
                    <ConfirmDialog
                        title="Remove this passkey?"
                        :description="`You can't sign in with '${passkey.name}' anymore after this.`"
                        confirm-label="Remove"
                        @confirm="removePasskey(passkey)"
                    >
                        <Button variant="ghost" size="icon" class="h-8 w-8" :title="`Remove ${passkey.name}`">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </ConfirmDialog>
                </div>
            </div>
            <p v-else class="text-sm text-muted-foreground">No passkeys yet.</p>

            <form v-if="supported" class="flex flex-col gap-2 sm:flex-row sm:items-end" @submit.prevent="addPasskey">
                <div class="grid flex-1 gap-2">
                    <Label for="passkey_name">Name</Label>
                    <Input id="passkey_name" v-model="name" name="passkey_name" placeholder="e.g. MacBook, Pixel" maxlength="255" />
                </div>
                <Button type="submit" :disabled="busy">
                    {{ busy ? 'Waiting for your device...' : 'Add passkey' }}
                </Button>
            </form>
            <p v-else class="text-sm text-muted-foreground">This browser doesn't support passkeys.</p>
            <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
        </CardContent>
    </Card>
</template>
