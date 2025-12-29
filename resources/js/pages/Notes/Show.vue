<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onUnmounted, onMounted } from 'vue';
import { useTrans } from '@/composables/useTrans';
import { useAuth } from '@/composables/useAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthModal from '@/components/AuthModal.vue';

const { t, locale } = useTrans();
const { isAuthenticated } = useAuth();

interface Recording {
    id: number;
    status: string;
    audio_path: string;
    duration_seconds: number | null;
    transcript: string | null;
    ai_title: string | null;
    ai_summary: string | null;
    ai_action_items: string[] | null;
    ai_meta: { error?: string; input_type?: string } | null;
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

const statusConfig = computed(() => ({
    processing: { label: t('status.processing'), class: 'bg-yellow-100 text-yellow-700', icon: 'spinner' },
    ready: { label: t('status.ready'), class: 'bg-green-100 text-green-700', icon: 'check' },
    failed: { label: t('status.failed'), class: 'bg-red-100 text-red-700', icon: 'error' },
}));

const currentStatus = computed(() => statusConfig.value[recording.value?.status as keyof typeof statusConfig.value] || statusConfig.value.processing);

// Build formatted share message for WhatsApp
const shareMessage = computed(() => {
    if (!recording.value || !shareUrl.value) return '';
    const title = recording.value.ai_title || t('notes.untitled');
    const summary = recording.value.ai_summary ? recording.value.ai_summary.slice(0, 150) + (recording.value.ai_summary.length > 150 ? '...' : '') : '';
    const items = (recording.value.ai_action_items || []).slice(0, 5);
    let msg = `📝 *${title}*\n\n`;
    if (summary) msg += `${summary}\n\n`;
    if (items.length > 0) {
        msg += `✅ Action Items:\n`;
        items.forEach(item => { msg += `• ${item}\n`; });
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
    if (pollInterval.value) return; // Already polling

    let attempts = 0;
    const maxAttempts = 120; // 4 minutes max

    pollInterval.value = window.setInterval(async () => {
        attempts++;
        if (attempts > maxAttempts) { stopPolling(); return; }

        try {
            const response = await fetch(`/recordings/${recording.value.id}`, {
                headers: { 'Accept': 'application/json' },
            });

            if (response.ok) {
                const data = await response.json();
                recording.value = data.recording;
                if (['ready', 'failed'].includes(data.recording.status)) {
                    stopPolling();
                }
            }
        } catch { /* Continue polling */ }
    }, 2000);
};

const stopPolling = () => {
    if (pollInterval.value) {
        clearInterval(pollInterval.value);
        pollInterval.value = null;
    }
};

// Retry processing for failed recordings
const retryProcessing = async () => {
    if (!recording.value || recording.value.status !== 'failed') return;

    try {
        const response = await fetch(`/recordings/${recording.value.id}/process`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
            },
        });

        if (response.ok) {
            recording.value.status = 'processing';
            startPolling();
        }
    } catch { /* Silent fail */ }
};

// Create share link
const createShare = async (): Promise<string | null> => {
    if (!recording.value) return null;
    if (shareUrl.value) return shareUrl.value;

    isSharing.value = true;
    try {
        const response = await fetch(`/recordings/${recording.value.id}/share`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
            },
        });
        if (response.ok) {
            const data = await response.json();
            shareUrl.value = data.url;
            return data.url;
        }
    } catch { /* Silent fail */ }
    finally { isSharing.value = false; }
    return null;
};

// Main share handler - 1-2 taps flow
const handleShareClick = async () => {
    if (!isAuthenticated.value) {
        showAuthModal.value = true;
        return;
    }

    isSharing.value = true;
    const url = await createShare();

    if (!url) {
        isSharing.value = false;
        return;
    }

    // Try native share first (mobile-first)
    if (navigator.share) {
        try {
            const title = recording.value?.ai_title || t('notes.untitled');
            const summary = recording.value?.ai_summary?.slice(0, 100) || '';
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

    // Fallback to modal
    isSharing.value = false;
    showShareSheet.value = true;
};

const copyShareUrl = async () => {
    if (!shareUrl.value) return;
    try {
        await navigator.clipboard.writeText(shareUrl.value);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    } catch {
        const input = document.createElement('input');
        input.value = shareUrl.value;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    }
};

const openWhatsApp = () => {
    if (whatsAppUrl.value) {
        window.open(whatsAppUrl.value, '_blank');
    }
};

onMounted(() => {
    if (page.props.flash?.auth_success && isAuthenticated.value) {
        handleShareClick();
    }
    // Auto-start polling if processing (AI processing is automatic now)
    if (recording.value?.status === 'processing') {
        startPolling();
    }
});

onUnmounted(() => { stopPolling(); });

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
            <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-sm border-b border-[#E5E7EB]">
                <div class="px-4 sm:px-6">
                    <div class="flex items-center justify-between h-14">
                        <Link :href="isAuthenticated ? '/dashboard' : '/'" class="flex items-center gap-1 text-[#64748B] hover:text-[#0F172A] transition-colors -ms-2 px-2 py-1">
                            <svg class="w-5 h-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            <span class="text-sm font-medium">{{ t('nav.back') }}</span>
                        </Link>

                        <button
                            v-if="isReady"
                            @click="handleShareClick"
                            :disabled="isSharing"
                            class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-[#4F46E5] hover:bg-[#4338CA] disabled:opacity-70 rounded-lg transition-colors"
                        >
                            <svg v-if="isSharing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                            </svg>
                            {{ t('share.button') }}
                        </button>
                    </div>
                </div>
            </header>
        </template>

        <div class="flex-1 px-4 sm:px-6 py-6">
            <template v-if="recording">
                <!-- Processing State -->
                <div v-if="isProcessing" class="flex flex-col items-center justify-center py-16 text-center">
                    <div class="w-16 h-16 bg-[#EEF2FF] rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-[#4F46E5] animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-[#0F172A] mb-1">{{ t('show.processing') }}</h2>
                    <p class="text-[#64748B]">{{ t('show.processingDesc') }}</p>
                </div>

                <!-- Ready State -->
                <template v-else-if="isReady">
                    <div class="mb-4">
                        <div class="flex items-center gap-2 text-sm text-[#64748B] mb-2">
                            <span>{{ formatDate(recording.created_at) }}</span>
                            <span v-if="recording.duration_seconds">•</span>
                            <span v-if="recording.duration_seconds">{{ formatDuration(recording.duration_seconds) }}</span>
                        </div>
                        <h1 class="text-xl font-semibold text-[#0F172A]">{{ recording.ai_title || t('notes.untitled') }}</h1>
                    </div>

                    <!-- Summary -->
                    <div v-if="recording.ai_summary" class="mb-6">
                        <h2 class="text-sm font-semibold text-[#64748B] uppercase tracking-wider mb-2">{{ t('summary.title') }}</h2>
                        <div class="bg-white border border-[#E5E7EB] rounded-xl p-4">
                            <p class="text-[#334155] leading-relaxed whitespace-pre-wrap">{{ recording.ai_summary }}</p>
                        </div>
                    </div>

                    <!-- Action Items -->
                    <div v-if="recording.ai_action_items?.length" class="mb-6">
                        <h2 class="text-sm font-semibold text-[#64748B] uppercase tracking-wider mb-2">
                            {{ t('actionItems.title') }}
                            <span class="text-[#64748B] font-normal">({{ recording.ai_action_items.length }})</span>
                        </h2>
                        <div class="bg-white border border-[#E5E7EB] rounded-xl divide-y divide-[#E5E7EB]">
                            <div v-for="(item, index) in recording.ai_action_items" :key="index" class="flex items-start gap-3 p-4">
                                <div class="w-5 h-5 mt-0.5 rounded border-2 border-[#E5E7EB] flex-shrink-0"></div>
                                <span class="text-[#334155]">{{ item }}</span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Failed State -->
                <div v-else-if="isFailed" class="flex flex-col items-center justify-center py-16 text-center">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-[#0F172A] mb-1">{{ t('show.failed') }}</h2>
                    <p class="text-[#64748B] mb-6">{{ t('show.failedDesc') }}</p>
                    <button
                        @click="retryProcessing"
                        class="px-5 py-2.5 text-sm font-medium text-white bg-[#4F46E5] hover:bg-[#4338CA] rounded-lg transition-colors"
                    >
                        {{ t('show.retryProcessing') }}
                    </button>
                </div>
            </template>

            <!-- Not Found -->
            <div v-else class="text-center py-16">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-[#0F172A] mb-2">{{ t('show.notFound') }}</h2>
                <p class="text-[#64748B] mb-6">{{ t('show.notFoundDesc') }}</p>
                <Link href="/" class="px-5 py-2.5 text-sm font-medium text-white bg-[#4F46E5] rounded-lg hover:bg-[#4338CA] transition-colors">
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
                <div v-if="showShareSheet" class="fixed inset-0 z-[100] bg-black/50" @click.self="showShareSheet = false">
                    <Transition
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="translate-y-full"
                        enter-to-class="translate-y-0"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="translate-y-0"
                        leave-to-class="translate-y-full"
                    >
                        <div v-if="showShareSheet" class="absolute bottom-0 inset-x-0 bg-white rounded-t-2xl" style="padding-bottom: env(safe-area-inset-bottom, 0px);">
                            <div class="flex justify-center pt-3 pb-2">
                                <div class="w-10 h-1 bg-[#E5E7EB] rounded-full"></div>
                            </div>

                            <div class="px-6 pb-6">
                                <h3 class="text-lg font-semibold text-[#0F172A] mb-4 text-center">{{ t('share.title') }}</h3>

                                <div class="space-y-3">
                                    <!-- Copy URL -->
                                    <div class="flex gap-2">
                                        <input
                                            type="text"
                                            :value="shareUrl"
                                            readonly
                                            class="flex-1 px-3 py-2.5 text-sm bg-[#F8FAFC] border border-[#E5E7EB] rounded-lg text-[#334155] truncate"
                                        />
                                        <button
                                            @click="copyShareUrl"
                                            class="px-4 py-2.5 text-sm font-medium rounded-lg transition-all flex items-center gap-1.5 min-w-[90px] justify-center"
                                            :class="copied ? 'bg-[#22C55E] text-white' : 'bg-[#4F46E5] text-white hover:bg-[#4338CA]'"
                                        >
                                            <svg v-if="copied" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                            {{ copied ? t('share.copied') : t('share.copy') }}
                                        </button>
                                    </div>

                                    <!-- WhatsApp Share -->
                                    <button
                                        @click="openWhatsApp"
                                        class="w-full px-4 py-3 text-sm font-medium text-white bg-[#25D366] hover:bg-[#20bd5a] rounded-lg transition-colors flex items-center justify-center gap-2"
                                    >
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                        </svg>
                                        Share via WhatsApp
                                    </button>

                                    <!-- Close button -->
                                    <button
                                        @click="showShareSheet = false"
                                        class="w-full px-4 py-3 text-sm font-medium text-[#64748B] hover:text-[#0F172A] hover:bg-[#F8FAFC] rounded-lg transition-colors"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- Auth Modal -->
        <AuthModal
            :show="showAuthModal"
            action="share"
            :redirect-to="`/notes/${recording?.id}`"
            @close="showAuthModal = false"
        />
    </AppLayout>
</template>
