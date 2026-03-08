import { ref, onMounted } from 'vue';

const STORAGE_KEY = 'tfw::client_id';

// Module-level state (shared across components)
const clientId = ref<string | null>(null);
const isLoading = ref(false);
let fingerprintPromise: Promise<string> | null = null;

// Pre-load fingerprint on module import (before any composable use)
if (typeof window !== 'undefined') {
    // Check localStorage first (sync)
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored) {
        clientId.value = stored;
    } else {
        // Start async fingerprint generation immediately
        import('@fingerprintjs/fingerprintjs').then(async (FingerprintJS) => {
            const fp = await FingerprintJS.load();
            const result = await fp.get();
            clientId.value = result.visitorId;
            localStorage.setItem(STORAGE_KEY, result.visitorId);
        }).catch(() => {
            // Fallback handled in getClientId()
        });
    }
}

/**
 * Composable for managing device identification using FingerprintJS.
 * Provides a stable client ID for device tracking and multi-device sync.
 */
export function useDeviceId() {
    /**
     * Get the client ID, generating one if needed.
     * Uses localStorage cache for performance, falls back to FingerprintJS.
     */
    async function getClientId(): Promise<string> {
        // Return cached value if available
        if (clientId.value) {
            return clientId.value;
        }

        // Check localStorage first (faster)
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            clientId.value = stored;
            return stored;
        }

        // Return existing promise if fingerprinting is in progress
        if (fingerprintPromise) {
            return fingerprintPromise;
        }

        // Generate new fingerprint
        isLoading.value = true;
        fingerprintPromise = generateFingerprint();

        try {
            const id = await fingerprintPromise;
            clientId.value = id;
            localStorage.setItem(STORAGE_KEY, id);
            return id;
        } finally {
            isLoading.value = false;
            fingerprintPromise = null;
        }
    }

    /**
     * Generate a fingerprint using FingerprintJS.
     * Falls back to a random ID if FingerprintJS is not available.
     */
    async function generateFingerprint(): Promise<string> {
        try {
            // Dynamic import for code splitting
            const FingerprintJS = await import('@fingerprintjs/fingerprintjs');
            const fp = await FingerprintJS.load();
            const result = await fp.get();
            return result.visitorId;
        } catch (error) {
            console.warn('FingerprintJS not available, using fallback:', error);
            // Fallback to random ID
            return generateRandomId();
        }
    }

    /**
     * Generate a random client ID as fallback.
     */
    function generateRandomId(): string {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        const array = new Uint8Array(32);
        crypto.getRandomValues(array);
        for (let i = 0; i < 32; i++) {
            result += chars[array[i] % chars.length];
        }
        return result;
    }

    /**
     * Clear the stored client ID (for testing/debugging).
     */
    function clearClientId(): void {
        clientId.value = null;
        localStorage.removeItem(STORAGE_KEY);
    }

    /**
     * Get headers with client ID for API requests.
     */
    async function getClientHeaders(): Promise<Record<string, string>> {
        const id = await getClientId();
        return {
            'X-Client-ID': id,
        };
    }

    // Initialize on mount
    onMounted(() => {
        // Pre-load client ID in background
        getClientId().catch(console.error);
    });

    return {
        clientId,
        isLoading,
        getClientId,
        clearClientId,
        getClientHeaders,
    };
}

/**
 * Standalone function to get client ID (for use outside Vue components).
 */
export async function getClientId(): Promise<string> {
    // Check localStorage first
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored) {
        return stored;
    }

    // Generate new fingerprint
    try {
        const FingerprintJS = await import('@fingerprintjs/fingerprintjs');
        const fp = await FingerprintJS.load();
        const result = await fp.get();
        localStorage.setItem(STORAGE_KEY, result.visitorId);
        return result.visitorId;
    } catch (error) {
        console.warn('FingerprintJS not available, using fallback:', error);
        // Fallback to random ID
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        const array = new Uint8Array(32);
        crypto.getRandomValues(array);
        for (let i = 0; i < 32; i++) {
            result += chars[array[i] % chars.length];
        }
        localStorage.setItem(STORAGE_KEY, result);
        return result;
    }
}
