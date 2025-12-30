/**
 * CSRF token helper for fetch requests.
 * Handles both meta tag and XSRF-TOKEN cookie methods.
 */
export function useCsrf() {
    /**
     * Get CSRF token from meta tag (primary) or XSRF-TOKEN cookie (fallback).
     */
    const getToken = (): string => {
        // Try meta tag first
        const metaToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        if (metaToken) {
            return metaToken;
        }

        // Fallback to XSRF-TOKEN cookie (Laravel sets this automatically)
        const cookies = document.cookie.split(';');
        for (const cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'XSRF-TOKEN' && value) {
                // Decode the URI-encoded token
                return decodeURIComponent(value);
            }
        }

        return '';
    };

    /**
     * Get headers object for fetch requests with CSRF token.
     * Use this for JSON requests.
     */
    const getJsonHeaders = (): HeadersInit => ({
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': getToken(),
    });

    /**
     * Get headers object for FormData requests (no Content-Type - browser sets it).
     */
    const getFormDataHeaders = (): HeadersInit => ({
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': getToken(),
        Accept: 'application/json',
    });

    /**
     * Perform a fetch request with CSRF protection and credentials.
     */
    const secureFetch = async (url: string, options: RequestInit = {}): Promise<Response> => {
        const isFormData = options.body instanceof FormData;
        const headers = isFormData ? getFormDataHeaders() : getJsonHeaders();

        return fetch(url, {
            ...options,
            headers: {
                ...headers,
                ...(options.headers || {}),
            },
            credentials: 'same-origin',
        });
    };

    return {
        getToken,
        getJsonHeaders,
        getFormDataHeaders,
        secureFetch,
    };
}

export default useCsrf;
