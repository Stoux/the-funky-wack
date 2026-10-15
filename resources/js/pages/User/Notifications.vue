<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Bell } from 'lucide-vue-next';
import UserMenu from '@/components/UserMenu.vue';
import { useNotifications } from '@/composables/useNotifications';
import { formatDuration } from '@/lib/utils';

const { notifications, unreadCount, fetchNotifications, markAsRead, markAllAsRead } = useNotifications();

onMounted(() => {
    fetchNotifications();
});

function getNotificationText(data: Record<string, unknown>): string {
    if (data.type === 'comment_reply') {
        return `${data.replier_name} replied to your comment on ${data.liveset_title}`;
    }
    if (data.type === 'new_comment') {
        return `${data.commenter_name} commented on ${data.liveset_title}`;
    }
    return 'New notification';
}
</script>

<template>
    <Head title="Notifications" />

    <div class="min-h-screen bg-background">
        <div class="max-w-2xl mx-auto px-4 py-6">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <Link :href="route('home')">
                        <Button variant="ghost" size="icon" class="rounded-full">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <h1 class="text-2xl font-bold">Notifications</h1>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        v-if="unreadCount > 0"
                        variant="outline"
                        size="sm"
                        @click="markAllAsRead"
                    >
                        Mark all read
                    </Button>
                    <UserMenu />
                </div>
            </div>

            <div class="space-y-1">
                <div
                    v-for="notification in notifications"
                    :key="notification.id"
                    class="flex items-start gap-3 p-3 rounded-lg transition-colors cursor-pointer"
                    :class="notification.read_at ? 'text-muted-foreground' : 'bg-muted/50'"
                    @click="!notification.read_at && markAsRead(notification.id)"
                >
                    <Bell class="h-4 w-4 mt-0.5 shrink-0" :class="notification.read_at ? 'text-muted-foreground' : 'text-primary'" />
                    <div class="flex-1 min-w-0">
                        <p class="text-sm">{{ getNotificationText(notification.data) }}</p>
                        <div class="flex items-center gap-2 text-xs text-muted-foreground mt-0.5">
                            <span v-if="notification.data.body_preview" class="truncate">
                                "{{ notification.data.body_preview }}"
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            {{ new Date(notification.created_at).toLocaleDateString() }}
                            {{ new Date(notification.created_at).toLocaleTimeString() }}
                        </p>
                    </div>
                    <span
                        v-if="notification.data.timestamp_seconds !== undefined"
                        class="text-xs font-mono text-muted-foreground"
                    >
                        {{ formatDuration(notification.data.timestamp_seconds as number) }}
                    </span>
                </div>

                <div v-if="notifications.length === 0" class="text-center py-12 text-muted-foreground">
                    No notifications yet.
                </div>
            </div>
        </div>
    </div>
</template>
