export class EventTracker {
    static track(name: string, metadata: Record<string, unknown> = {}): void {
        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

            fetch('/api/events', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                body: JSON.stringify({ name, metadata }),
            }).catch(() => {
                // Silent fail - tracking should never break the app
            });
        } catch {
            // Silent fail
        }
    }
}

export default EventTracker;
