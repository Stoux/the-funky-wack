import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import type { User } from '@/types';

export function useAuth() {
    const page = usePage();

    const user = computed<User | null>(() => {
        const auth = page.props.auth as { user: User | null } | undefined;
        return auth?.user ?? null;
    });

    const isAuthenticated = computed(() => !!user.value);

    const logout = () => {
        router.post(route('auth.logout'));
    };

    return {
        user,
        isAuthenticated,
        logout,
    };
}
