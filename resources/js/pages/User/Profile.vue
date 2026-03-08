<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowLeft, Copy, Check, Plus, UserCheck } from 'lucide-vue-next';
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
