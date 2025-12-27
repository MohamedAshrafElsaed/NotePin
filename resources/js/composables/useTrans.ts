import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface PageProps {
    locale: string;
    locales: string[];
    translations: Record<string, string>;
    [key: string]: unknown;
}

export function useTrans() {
    const page = usePage<PageProps>();

    const locale = computed(() => page.props.locale ?? 'ar');
    const locales = computed(() => page.props.locales ?? ['ar', 'en']);
    const isRTL = computed(() => locale.value === 'ar');

    /**
     * Translate a key with optional replacements
     * @param key - Translation key (e.g., 'app.name')
     * @param replacements - Object with replacement values (e.g., { count: 5 })
     * @returns Translated string or key if not found
     */
    const t = (key: string, replacements: Record<string, string | number> = {}): string => {
        const translations = page.props.translations ?? {};
        let translation = translations[key] ?? key;

        // Handle Laravel-style replacements (:key)
        Object.entries(replacements).forEach(([k, v]) => {
            translation = translation.replace(new RegExp(`:${k}`, 'g'), String(v));
        });

        return translation;
    };

    /**
     * Check if a translation key exists
     */
    const te = (key: string): boolean => {
        const translations = page.props.translations ?? {};
        return key in translations;
    };

    /**
     * Get current text direction
     */
    const dir = computed(() => isRTL.value ? 'rtl' : 'ltr');

    /**
     * Get locale for date formatting
     */
    const dateLocale = computed(() => locale.value === 'ar' ? 'ar-EG' : 'en-US');

    return {
        t,
        te,
        locale,
        locales,
        isRTL,
        dir,
        dateLocale,
    };
}

export default useTrans;
