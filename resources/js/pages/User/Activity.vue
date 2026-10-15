<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-vue-next';
import UserMenu from '@/components/UserMenu.vue';
import UserInitials from '@/components/UserInitials.vue';
import { formatDuration } from '@/lib/utils';

interface ActivityComment {
    id: number;
    user: { id: number; name: string } | null;
    liveset: { id: number; title: string; artist_name: string } | null;
    body: string;
    timestamp_seconds: number;
    created_at: string;
}

const comments = ref<ActivityComment[]>([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const response = await fetch('/api/comments/recent', {
            headers: { 'Accept': 'application/json' },
            credentials: 'include',
        });
        if (response.ok) {
            const data = await response.json();
            comments.value = data.comments;
        }
    } catch (error) {
        console.error('Failed to load activity:', error);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Head title="Activity" />

    <div class="min-h-screen bg-background">
        <div class="max-w-2xl mx-auto px-4 py-6">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <Link :href="route('home')">
                        <Button variant="ghost" size="icon" class="rounded-full">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <h1 class="text-2xl font-bold">Recent Activity</h1>
                </div>
                <UserMenu />
            </div>

            <div v-if="loading" class="text-center py-12 text-muted-foreground">
                Loading...
            </div>

            <div v-else class="space-y-1">
                <div
                    v-for="comment in comments"
                    :key="comment.id"
                    class="flex items-start gap-3 p-3 rounded-lg hover:bg-muted/50 transition-colors"
                >
                    <UserInitials
                        v-if="comment.user"
                        :user-id="comment.user.id"
                        :name="comment.user.name"
                        size="md"
                    />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="font-medium">{{ comment.user?.name }}</span>
                            <span class="text-muted-foreground">on</span>
                            <span class="font-medium truncate">{{ comment.liveset?.title }}</span>
                        </div>
                        <p class="text-sm text-muted-foreground mt-0.5 line-clamp-2">{{ comment.body }}</p>
                        <div class="flex items-center gap-2 text-xs text-muted-foreground mt-1">
                            <span class="font-mono">{{ formatDuration(comment.timestamp_seconds) }}</span>
                            <span>&bull;</span>
                            <span>{{ new Date(comment.created_at).toLocaleDateString() }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="comments.length === 0" class="text-center py-12 text-muted-foreground">
                    No recent activity.
                </div>
            </div>
        </div>
    </div>
</template>
