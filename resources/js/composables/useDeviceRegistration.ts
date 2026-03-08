import { ref, watch } from 'vue';
import { useAuth } from '@/composables/useAuth';
import { getClientId } from '@/composables/useDeviceId';

// Module-level state - only register once per page load
let hasRegistered = false;

/**
 * Auto-registers the current device for logged-in users.
 * Should be called once at app initialization.
 */
export function useDeviceRegistration() {
    const { isAuthenticated } = useAuth();
    const isRegistering = ref(false);

    async function registerDevice(): Promise<void> {
        if (hasRegistered || isRegistering.value) {
            return;
        }

        if (!isAuthenticated.value) {
            return;
        }

        isRegistering.value = true;

        try {
            const clientId = await getClientId();

            const response = await fetch('/api/devices/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
                body: JSON.stringify({ client_id: clientId }),
            });

            if (response.ok) {
                hasRegistered = true;
            }
        } catch (error) {
            console.error('Failed to register device:', error);
        } finally {
            isRegistering.value = false;
        }
    }

    // Auto-register when auth state becomes true
    watch(isAuthenticated, (authenticated) => {
        if (authenticated && !hasRegistered) {
            registerDevice();
        }
    }, { immediate: true });

    return {
        registerDevice,
        isRegistering,
    };
}

function getCsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find(row => row.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}
