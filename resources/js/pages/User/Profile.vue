<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowLeft, Copy, Check, Plus, UserCheck, Eye } from 'lucide-vue-next';
import type { ListeningVisibility } from '@/types';
import UserMenu from '@/components/UserMenu.vue';
import { useAuth } from '@/composables/useAuth';

interface InviteCode {
    id: number;
    code: string;
    used_by: { id: number; name: string } | null;
    used_at: string | null;
    created_at: string;
}

const props = defineProps<{
    invites: InviteCode[];
}>();

const { user } = useAuth();
const invites = ref<InviteCode[]>(props.invites);
const copiedCode = ref<string | null>(null);
const generating = ref(false);

// Listening visibility
const listeningVisibility = ref<ListeningVisibility>(
    (user.value?.listening_visibility as ListeningVisibility) ?? 'everyone'
);
const savingVisibility = ref(false);

async function updateVisibility(value: ListeningVisibility) {
    listeningVisibility.value = value;
    savingVisibility.value = true;
    try {
        await fetch('/api/settings/visibility', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
            body: JSON.stringify({ listening_visibility: value }),
        });
    } catch (error) {
        console.error('Failed to update visibility:', error);
    } finally {
        savingVisibility.value = false;
    }
}

async function generateInviteCode() {
    generating.value = true;
    try {
        const response = await fetch('/api/invites', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
        });

        if (response.ok) {
            const data = await response.json();
            invites.value.unshift({
                ...data.invite,
                used_by: null,
                used_at: null,
            });
        }
    } catch (error) {
        console.error('Failed to generate invite code:', error);
    } finally {
        generating.value = false;
    }
}

async function copyCode(code: string) {
    try {
        await navigator.clipboard.writeText(code);
        copiedCode.value = code;
        setTimeout(() => {
            copiedCode.value = null;
        }, 2000);
    } catch (error) {
        console.error('Failed to copy:', error);
    }
}

function formatDate(dateString: string): string {
    const date = new Date(dateString);
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
</script>

<template>
    <Head title="Profile" />

    <div class="min-h-screen p-4">
        <div class="max-w-4xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('home')">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <h1 class="text-2xl font-bold">Profile</h1>
                </div>
                <UserMenu />
            </div>

            <!-- User Info -->
            <Card>
                <CardHeader>
                    <CardTitle>Account</CardTitle>
                    <CardDescription>Your account information</CardDescription>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div>
                        <p class="text-sm text-muted-foreground">Name</p>
                        <p class="font-medium">{{ user?.name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Email</p>
                        <p class="font-medium">{{ user?.email }}</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Listening Visibility -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center space-x-2">
                        <Eye class="h-5 w-5" />
                        <span>Listening Visibility</span>
                    </CardTitle>
                    <CardDescription>Control who can see your name on the Live page when you're listening</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-2">
                        <label
                            v-for="option in [
                                { value: 'everyone', label: 'Everyone', description: 'Anyone can see your name' },
                                { value: 'authenticated', label: 'Logged-in users only', description: 'Only registered users can see your name' },
                                { value: 'nobody', label: 'Nobody', description: 'You appear as Anonymous' },
                            ]"
                            :key="option.value"
                            class="flex items-start space-x-3 p-3 rounded-lg border cursor-pointer hover:bg-muted/50 transition-colors"
                            :class="{ 'border-primary bg-primary/5': listeningVisibility === option.value }"
                        >
                            <input
                                type="radio"
                                name="listening_visibility"
                                :value="option.value"
                                :checked="listeningVisibility === option.value"
                                :disabled="savingVisibility"
                                class="mt-1"
                                @change="updateVisibility(option.value as ListeningVisibility)"
                            />
                            <div>
                                <p class="font-medium text-sm">{{ option.label }}</p>
                                <p class="text-xs text-muted-foreground">{{ option.description }}</p>
                            </div>
                        </label>
                    </div>
                </CardContent>
            </Card>

            <!-- Invite Codes -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Invite Codes</CardTitle>
                            <CardDescription>Invite your friends to join</CardDescription>
                        </div>
                        <Button @click="generateInviteCode" :disabled="generating" size="sm">
                            <Plus class="h-4 w-4 mr-2" />
                            {{ generating ? 'Generating...' : 'Generate Code' }}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="invites.length === 0" class="text-center py-4">
                        <p class="text-muted-foreground">No invite codes yet.</p>
                        <p class="text-sm text-muted-foreground">Generate one to invite a friend!</p>
                    </div>

                    <div v-else class="space-y-2">
                        <div
                            v-for="invite in invites"
                            :key="invite.id"
                            class="flex items-center justify-between p-3 rounded-lg border"
                            :class="{ 'opacity-60': invite.used_by }"
                        >
                            <div class="flex items-center space-x-3">
                                <code class="font-mono text-lg tracking-wider">{{ invite.code }}</code>
                                <Button
                                    v-if="!invite.used_by"
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8"
                                    @click="copyCode(invite.code)"
                                >
                                    <Check v-if="copiedCode === invite.code" class="h-4 w-4 text-green-500" />
                                    <Copy v-else class="h-4 w-4" />
                                </Button>
                            </div>
                            <div class="text-right text-sm">
                                <div v-if="invite.used_by" class="flex items-center space-x-2 text-muted-foreground">
                                    <UserCheck class="h-4 w-4" />
                                    <span>Used by {{ invite.used_by.name }}</span>
                                </div>
                                <p class="text-muted-foreground">{{ formatDate(invite.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
