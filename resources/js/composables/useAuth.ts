import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface User {
    id: number;
    name: string | null;
    email: string | null;
    avatar: string | null;
}

interface AuthProps {
    user: User | null;
    isAuthenticated: boolean;
    recordingCount: number;
}

interface PageProps {
    auth: AuthProps;
    [key: string]: unknown;
}

export function useAuth() {
    const page = usePage<PageProps>();

    const user = computed(() => page.props.auth?.user ?? null);
    const isAuthenticated = computed(() => page.props.auth?.isAuthenticated ?? false);
    const recordingCount = computed(() => page.props.auth?.recordingCount ?? 0);

    const getAnonymousId = (): string => {
        if (typeof window === 'undefined') return '';

        let id = localStorage.getItem('notepin_anon_id');
        if (!id) {
            id = crypto.randomUUID();
            localStorage.setItem('notepin_anon_id', id);
        }
        return id;
    };

    const clearAnonymousId = (): void => {
        if (typeof window === 'undefined') return;
        localStorage.removeItem('notepin_anon_id');
    };

    return {
        user,
        isAuthenticated,
        recordingCount,
        getAnonymousId,
        clearAnonymousId,
    };
}

export default useAuth;
