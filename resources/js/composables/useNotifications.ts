import { ref } from 'vue';
import { useAuth } from './useAuth';
import type { AppNotification } from '@/types';

const notifications = ref<AppNotification[]>([]);
const unreadCount = ref(0);
const loaded = ref(false);

export function useNotifications() {
    const { isAuthenticated } = useAuth();

    async function fetchNotifications(): Promise<void> {
        if (!isAuthenticated.value) {
            return;
        }

        try {
            const response = await fetch('/api/notifications', {
                headers: { 'Accept': 'application/json' },
                credentials: 'include',
            });

            if (response.ok) {
                const data = await response.json();
                notifications.value = data.notifications;
                unreadCount.value = data.unread_count;
                loaded.value = true;
            }
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
        }
    }

    async function markAsRead(id: string): Promise<void> {
        try {
            const response = await fetch(`/api/notifications/${id}/read`, {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
            });

            if (response.ok) {
                const notification = notifications.value.find(n => n.id === id);
                if (notification && !notification.read_at) {
                    notification.read_at = new Date().toISOString();
                    unreadCount.value = Math.max(0, unreadCount.value - 1);
                }
            }
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    }

    async function markAllAsRead(): Promise<void> {
        try {
            const response = await fetch('/api/notifications/read-all', {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
            });

            if (response.ok) {
                for (const n of notifications.value) {
                    if (!n.read_at) {
                        n.read_at = new Date().toISOString();
                    }
                }
                unreadCount.value = 0;
            }
        } catch (error) {
            console.error('Failed to mark all notifications as read:', error);
        }
    }

    function getCsrfToken(): string {
        const cookie = document.cookie
            .split('; ')
            .find(row => row.startsWith('XSRF-TOKEN='));
        return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
    }

    return {
        notifications,
        unreadCount,
        loaded,
        fetchNotifications,
        markAsRead,
        markAllAsRead,
    };
}
