<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onUnmounted, onMounted } from 'vue';
import { useTrans } from '@/composables/useTrans';
import { useAuth } from '@/composables/useAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthModal from '@/components/AuthModal.vue';

const { t, locale } = useTrans();
const { isAuthenticated } = useAuth();

interface ActionItemFull {
    task: string;
    due_date: string | null;
    owner: string | null;
    project: string | null;
    confidence: 'low' | 'medium' | 'high';
}

interface Recording {
    id: number;
    status: string;
    audio_path: string;
    duration_seconds: number | null;
    transcript: string | null;
    ai_title: string | null;
    ai_summary: string | null;
    ai_action_items: string[] | null;
    ai_meta: {
        error?: string;
        input_type?: string;
        language?: string;
        source?: string;
        decision_context?: string;
        action_items_full?: ActionItemFull[];
    } | null;
    created_at: string;
}

const page = usePage<{ recording: Recording; flash?: { auth_success?: boolean } }>();
const recording = ref<Recording>(page.props.recording);
const pollInterval = ref<number | null>(null);

const shareUrl = ref<string | null>(null);
const isSharing = ref(false);
const copied = ref(false);
const showShareSheet = ref(false);
const showAuthModal = ref(false);

const isReady = computed(() => recording.value?.status === 'ready');
const isFailed = computed(() => recording.value?.status === 'failed');
const isProcessing = computed(() => recording.value?.status === 'processing');

// Get full action items with metadata if available
const actionItemsFull = computed<ActionItemFull[]>(() => {
    const full = recording.value?.ai_meta?.action_items_full;
    if (full && Array.isArray(full)) return full;

    // Fallback to simple strings
    const simple = recording.value?.ai_action_items;
    if (simple && Array.isArray(simple)) {
        return simple.map((task) => ({
            task: typeof task === 'string' ? task : String(task),
            due_date: null,
            owner: null,
            project: null,
            confidence: 'medium' as const,
        }));
    }
    return [];
});

const statusConfig = computed(() => ({
    processing: { label: t('status.processing'), class: 'bg-yellow-100 text-yellow-700', icon: 'spinner' },
    ready: { label: t('status.ready'), class: 'bg-green-100 text-green-700', icon: 'check' },
    failed: { label: t('status.failed'), class: 'bg-red-100 text-red-700', icon: 'error' },
}));

const currentStatus = computed(() => statusConfig.value[recording.value?.status as keyof typeof statusConfig.value] || statusConfig.value.processing);

const shareMessage = computed(() => {
    if (!recording.value || !shareUrl.value) return '';
    const title = recording.value.ai_title || t('notes.untitled');
    const summary = recording.value.ai_summary
        ? recording.value.ai_summary.slice(0, 150) + (recording.value.ai_summary.length > 150 ? '...' : '')
        : '';
    const items = actionItemsFull.value.slice(0, 5);
    let msg = `📝 *${title}*\n\n`;
    if (summary) msg += `${summary}\n\n`;
    if (items.length > 0) {
        msg += `✅ Action Items:\n`;
        items.forEach((item) => {
            msg += `• ${item.task}\n`;
        });
        msg += '\n';
    }
    msg += `🔗 ${shareUrl.value}`;
    return msg;
});

const whatsAppUrl = computed(() => {
    if (!shareMessage.value) return '';
    return `https://wa.me/?text=${encodeURIComponent(shareMessage.value)}`;
});

const startPolling = () => {
    if (pollInterval.value) return;

    let attempts = 0;
    const maxAttempts = 120;

    pollInterval.value = window.setInterval(async () => {
        attempts++;
        if (attempts > maxAttempts) {
            stopPolling();
            return;
        }

        try {
            const response = await fetch(`/recordings/${recording.value.id}`, {
                headers: { Accept: 'application/json' },
            });

            if (response.ok) {
                const data = await response.json();
                recording.value = data.recording;
                if (['ready', 'failed'].includes(data.recording.status)) {
                    stopPolling();
                }
            }
        } catch {
            /* Continue polling */
        }
    }, 2000);
};

const stopPolling = () => {
    if (pollInterval.value) {
        clearInterval(pollInterval.value);
        pollInterval.value = null;
    }
};

const retryProcessing = async () => {
    if (!recording.value || recording.value.status !== 'failed') return;

    try {
        const response = await fetch(`/recordings/${recording.value.id}/process`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
            },
        });

        if (response.ok) {
            recording.value.status = 'processing';
            startPolling();
        }
    } catch {
        /* Silent fail */
    }
};

const createShare = async (): Promise<string | null> => {
    if (!recording.value) return null;
    if (shareUrl.value) return shareUrl.value;

    isSharing.value = true;
    try {
        const response = await fetch(`/recordings/${recording.value.id}/share`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
            },
        });

        if (!response.ok) throw new Error('Failed to create share');

        const data = await response.json();
        shareUrl.value = data.url;
        return data.url;
    } catch {
        return null;
    } finally {
        isSharing.value = false;
    }
};

const handleShareClick = async () => {
    if (!isAuthenticated.value) {
        showAuthModal.value = true;
        return;
    }

    const url = await createShare();
    if (!url) return;

    if (navigator.share) {
        const title = recording.value?.ai_title || t('notes.untitled');
        const summary = recording.value?.ai_summary?.slice(0, 100) || '';
        try {
            await navigator.share({
                title: title,
                text: summary ? `${title}\n\n${summary}...` : title,
                url: url,
            });
            isSharing.value = false;
            return;
        } catch (err) {
            if ((err as Error).name === 'AbortError') {
                isSharing.value = false;
                return;
            }
        }
    }

    isSharing.value = false;
    showShareSheet.value = true;
};

const copyShareUrl = async () => {
    if (!shareUrl.value) return;
    try {
        await navigator.clipboard.writeText(shareUrl.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        const input = document.createElement('input');
        input.value = shareUrl.value;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    }
};

const openWhatsApp = () => {
    if (whatsAppUrl.value) {
        window.open(whatsAppUrl.value, '_blank');
    }
};

const getConfidenceColor = (confidence: string) => {
    switch (confidence) {
        case 'high':
            return 'bg-green-100 text-green-700';
        case 'medium':
            return 'bg-yellow-100 text-yellow-700';
        case 'low':
            return 'bg-gray-100 text-gray-600';
        default:
            return 'bg-gray-100 text-gray-600';
    }
};

onMounted(() => {
    if (page.props.flash?.auth_success && isAuthenticated.value) {
        handleShareClick();
    }
    if (recording.value?.status === 'processing') {
        startPolling();
    }
});

onUnmounted(() => {
    stopPolling();
});

const formatDuration = (seconds: number | null | undefined) => {
    if (!seconds) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
};

const formatDate = (dateStr: string | undefined) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const now = new Date();
    const diffDays = Math.floor((now.getTime() - date.getTime()) / 86400000);
    if (diffDays === 0) return t('time.today');
    if (diffDays === 1) return t('time.yesterday');
    return date.toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', { month: 'short', day: 'numeric' });
};
</script>

<template>
    <Head :title="recording?.ai_title || t('notes.untitled')" />

    <AppLayout :show-fab="true">
        <template #header>
            <header class="sticky top-0 z-40 border-b border-[#E5E7EB] bg-white/95 backdrop-blur-sm">
                <div class="px-4 sm:px-6">
                    <div class="flex h-14 items-center justify-between">
                        <Link
                            :href="isAuthenticated ? '/dashboard' : '/'"
                            class="-ms-2 flex items-center gap-1 px-2 py-1 text-[#64748B] transition-colors hover:text-[#0F172A]"
                        >
                            <svg class="h-5 w-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            <span class="text-sm font-medium">{{ t('nav.back') }}</span>
                        </Link>

                        <button
                            v-if="isReady"
                            @click="handleShareClick"
                            :disabled="isSharing"
                            class="flex items-center gap-1.5 rounded-lg bg-[#4F46E5] px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-[#4338CA] disabled:opacity-70"
                        >
                            <svg v-if="isSharing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                />
                            </svg>
                            <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"
                                />
                            </svg>
                            {{ t('share.button') }}
                        </button>
                    </div>
                </div>
            </header>
        </template>

        <div class="mx-auto w-full max-w-2xl px-4 py-6 sm:px-6">
            <!-- Processing State -->
            <div v-if="isProcessing" class="flex flex-col items-center justify-center py-16 text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[#EEF2FF]">
                    <svg class="h-8 w-8 animate-spin text-[#4F46E5]" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        />
                    </svg>
                </div>
                <h2 class="mb-1 text-lg font-semibold text-[#0F172A]">{{ t('show.processing') }}</h2>
                <p class="text-[#64748B]">{{ t('show.processingDesc') }}</p>
            </div>

            <!-- Ready State -->
            <template v-else-if="isReady">
                <!-- Header -->
                <div class="mb-6">
                    <h1 class="mb-2 text-2xl font-bold text-[#0F172A]">{{ recording.ai_title || t('notes.untitled') }}</h1>
                    <div class="flex items-center gap-3 text-sm text-[#64748B]">
                        <span>{{ formatDate(recording.created_at) }}</span>
                        <span v-if="recording.duration_seconds">•</span>
                        <span v-if="recording.duration_seconds">{{ formatDuration(recording.duration_seconds) }}</span>
                        <span v-if="recording.ai_meta?.language">•</span>
                        <span v-if="recording.ai_meta?.language" class="uppercase">{{ recording.ai_meta.language }}</span>
                    </div>
                </div>

                <!-- Decision Context -->
                <div v-if="recording.ai_meta?.decision_context" class="mb-6 rounded-xl border border-[#C7D2FE] bg-[#EEF2FF] p-4">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-[#4F46E5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        <p class="text-sm text-[#4338CA]">{{ recording.ai_meta.decision_context }}</p>
                    </div>
                </div>

                <!-- Summary -->
                <div class="mb-6">
                    <h2 class="mb-2 text-sm font-semibold tracking-wider text-[#64748B] uppercase">{{ t('summary.title') }}</h2>
                    <div class="rounded-xl border border-[#E5E7EB] bg-white p-4">
                        <p class="leading-relaxed whitespace-pre-wrap text-[#334155]">{{ recording.ai_summary }}</p>
                    </div>
                </div>

                <!-- Action Items -->
                <div v-if="actionItemsFull.length" class="mb-6">
                    <h2 class="mb-2 text-sm font-semibold tracking-wider text-[#64748B] uppercase">
                        {{ t('actionItems.title') }}
                        <span class="font-normal text-[#64748B]">({{ actionItemsFull.length }})</span>
                    </h2>
                    <div class="divide-y divide-[#E5E7EB] rounded-xl border border-[#E5E7EB] bg-white">
                        <div v-for="(item, index) in actionItemsFull" :key="index" class="p-4">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 h-5 w-5 flex-shrink-0 rounded border-2 border-[#E5E7EB]"></div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[#334155]">{{ item.task }}</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <span
                                            v-if="item.owner"
                                            class="inline-flex items-center gap-1 rounded-full bg-[#F1F5F9] px-2 py-0.5 text-xs text-[#475569]"
                                        >
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                                />
                                            </svg>
                                            {{ item.owner }}
                                        </span>
                                        <span
                                            v-if="item.due_date"
                                            class="inline-flex items-center gap-1 rounded-full bg-[#FEF3C7] px-2 py-0.5 text-xs text-[#92400E]"
                                        >
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                                />
                                            </svg>
                                            {{ item.due_date }}
                                        </span>
                                        <span
                                            v-if="item.project"
                                            class="inline-flex items-center gap-1 rounded-full bg-[#DBEAFE] px-2 py-0.5 text-xs text-[#1E40AF]"
                                        >
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"
                                                />
                                            </svg>
                                            {{ item.project }}
                                        </span>
                                        <span :class="getConfidenceColor(item.confidence)" class="rounded-full px-2 py-0.5 text-xs">
                                            {{ item.confidence }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Failed State -->
            <div v-else-if="isFailed" class="flex flex-col items-center justify-center py-16 text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
                    <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                        />
                    </svg>
                </div>
                <h2 class="mb-1 text-lg font-semibold text-[#0F172A]">{{ t('show.failed') }}</h2>
                <p class="mb-6 text-[#64748B]">{{ t('show.failedDesc') }}</p>
                <button
                    @click="retryProcessing"
                    class="rounded-lg bg-[#4F46E5] px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-[#4338CA]"
                >
                    {{ t('show.retryProcessing') }}
                </button>
            </div>

            <!-- Not Found -->
            <div v-else class="py-16 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
                    <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <h2 class="mb-2 text-xl font-semibold text-[#0F172A]">{{ t('show.notFound') }}</h2>
                <p class="mb-6 text-[#64748B]">{{ t('show.notFoundDesc') }}</p>
                <Link href="/" class="rounded-lg bg-[#4F46E5] px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-[#4338CA]">
                    {{ t('show.goHome') }}
                </Link>
            </div>
        </div>

        <!-- Share Bottom Sheet -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showShareSheet" class="fixed inset-0 z-50 bg-black/50" @click.self="showShareSheet = false">
                    <Transition
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="translate-y-full"
                        enter-to-class="translate-y-0"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="translate-y-0"
                        leave-to-class="translate-y-full"
                    >
                        <div v-if="showShareSheet" class="pb-safe absolute right-0 bottom-0 left-0 rounded-t-3xl bg-white p-6">
                            <div class="mb-4 flex justify-center">
                                <div class="h-1 w-10 rounded-full bg-[#E5E7EB]"></div>
                            </div>

                            <h3 class="mb-4 text-center text-lg font-semibold text-[#0F172A]">{{ t('share.title') }}</h3>

                            <!-- Share URL -->
                            <div class="mb-4 flex items-center gap-2 rounded-xl bg-[#F8FAFC] p-3">
                                <input type="text" :value="shareUrl" readonly class="flex-1 bg-transparent text-sm text-[#334155] outline-none" />
                                <button
                                    @click="copyShareUrl"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
                                    :class="copied ? 'bg-green-100 text-green-700' : 'bg-[#4F46E5] text-white hover:bg-[#4338CA]'"
                                >
                                    {{ copied ? t('share.copied') : t('share.copy') }}
                                </button>
                            </div>

                            <!-- WhatsApp -->
                            <button
                                @click="openWhatsApp"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#25D366] px-4 py-3 font-medium text-white transition-colors hover:bg-[#20BD5A]"
                            >
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"
                                    />
                                </svg>
                                WhatsApp
                            </button>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- Auth Modal -->
        <AuthModal :show="showAuthModal" @close="showAuthModal = false" />
    </AppLayout>
</template>
