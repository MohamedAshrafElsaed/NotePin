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
    ai_meta: { error?: string } | null;
    created_at: string;
}

const page = usePage<{ recording: Recording; flash?: { auth_success?: boolean } }>();
const recording = ref<Recording>(page.props.recording);
const isProcessing = ref(false);
const pollInterval = ref<number | null>(null);

const shareUrl = ref<string | null>(null);
const isSharing = ref(false);
const copied = ref(false);
const showShareSheet = ref(false);
const showAuthModal = ref(false);

const canProcess = computed(() =>
    recording.value && ['uploaded', 'failed'].includes(recording.value.status)
);

const isReady = computed(() => recording.value?.status === 'ready');
const isFailed = computed(() => recording.value?.status === 'failed');
const isPolling = computed(() => recording.value?.status === 'processing' || isProcessing.value);

const statusConfig = computed(() => ({
    uploaded: { label: t('status.uploaded'), class: 'bg-blue-100 text-blue-700', icon: 'upload' },
    processing: { label: t('status.processing'), class: 'bg-yellow-100 text-yellow-700', icon: 'spinner' },
    ready: { label: t('status.ready'), class: 'bg-green-100 text-green-700', icon: 'check' },
    failed: { label: t('status.failed'), class: 'bg-red-100 text-red-700', icon: 'error' },
}));

const currentStatus = computed(() => statusConfig.value[recording.value?.status as keyof typeof statusConfig.value] || statusConfig.value.uploaded);

const startProcessing = async () => {
    if (!recording.value) return;
    isProcessing.value = true;

    try {
        const response = await fetch(`/recordings/${recording.value.id}/process`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
            },
        });

        if (!response.ok) throw new Error('Failed to start processing');
        recording.value.status = 'processing';
        startPolling();
    } catch {
        isProcessing.value = false;
    }
};

const startPolling = () => {
    let attempts = 0;
    const maxAttempts = 60;

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
                if (['ready', 'failed'].includes(data.recording.status)) stopPolling();
            }
        } catch { /* Continue polling */ }
    }, 2000);
};

const stopPolling = () => {
    isProcessing.value = false;
    if (pollInterval.value) { clearInterval(pollInterval.value); pollInterval.value = null; }
};

const handleShareClick = () => {
    if (!isAuthenticated.value) {
        showAuthModal.value = true;
        return;
    }
    showShareSheet.value = true;
    if (!shareUrl.value) createShare();
};

const createShare = async () => {
    if (!recording.value || isSharing.value) return;
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
        }
    } catch { /* Silent fail */ }
    finally { isSharing.value = false; }
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

const nativeShare = async () => {
    if (!shareUrl.value || !navigator.share) return;
    try {
        await navigator.share({
            title: recording.value?.ai_title || t('notes.untitled'),
            url: shareUrl.value,
        });
    } catch { /* User cancelled or error */ }
};

onMounted(() => {
    if (page.props.flash?.auth_success && isAuthenticated.value) {
        showShareSheet.value = true;
        createShare();
    }
    // Auto-start processing if uploaded
    if (recording.value?.status === 'uploaded') {
        startProcessing();
    } else if (recording.value?.status === 'processing') {
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

    return date.toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head :title="recording?.ai_title || t('notes.untitled')" />

    <AppLayout :show-fab="true">
        <template #header>
            <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-sm border-b border-[#E5E7EB]">
                <div class="px-4 sm:px-6">
                    <div class="flex items-center justify-between h-14">
                        <!-- Back button -->
                        <Link
                            :href="isAuthenticated ? '/dashboard' : '/'"
                            class="flex items-center gap-1 text-[#64748B] hover:text-[#0F172A] transition-colors -ms-2 px-2 py-1"
                        >
                            <svg class="w-5 h-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            <span class="text-sm font-medium">{{ t('nav.back') }}</span>
                        </Link>

                        <!-- Share button (only when ready) -->
                        <button
                            v-if="isReady"
                            @click="handleShareClick"
                            class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-[#4F46E5] hover:bg-[#4338CA] rounded-lg transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                <div v-if="isPolling" class="flex flex-col items-center justify-center py-16 text-center">
                    <div class="w-16 h-16 mb-6 relative">
                        <svg class="w-16 h-16 animate-spin text-[#4F46E5]" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-semibold text-[#0F172A] mb-2">{{ t('show.processing') }}</h2>
                    <p class="text-[#64748B] max-w-xs">{{ t('show.processingDesc') }}</p>

                    <div class="flex justify-center gap-1 mt-6">
                        <div class="w-2 h-2 bg-[#4F46E5] rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                        <div class="w-2 h-2 bg-[#4F46E5] rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                        <div class="w-2 h-2 bg-[#4F46E5] rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                    </div>
                </div>

                <!-- Ready State - Note View -->
                <template v-else-if="isReady">
                    <!-- Note Card -->
                    <article class="bg-white border border-[#E5E7EB] rounded-2xl overflow-hidden">
                        <!-- Note Header -->
                        <div class="p-5 sm:p-6 border-b border-[#E5E7EB]">
                            <h1 class="text-xl sm:text-2xl font-semibold text-[#0F172A] mb-3">
                                {{ recording.ai_title || t('notes.untitled') }}
                            </h1>
                            <div class="flex flex-wrap items-center gap-3 text-sm text-[#64748B]">
                                <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', currentStatus.class]">
                                    {{ currentStatus.label }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ formatDuration(recording.duration_seconds) }}
                                </span>
                                <span>{{ formatDate(recording.created_at) }}</span>
                            </div>
                        </div>

                        <!-- Summary Section -->
                        <div class="p-5 sm:p-6 border-b border-[#E5E7EB]">
                            <div class="flex items-center gap-2 mb-3">
                                <svg class="w-5 h-5 text-[#4F46E5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <h2 class="font-semibold text-[#0F172A]">{{ t('summary.title') }}</h2>
                            </div>
                            <p class="text-[#334155] leading-relaxed whitespace-pre-line">{{ recording.ai_summary }}</p>
                        </div>

                        <!-- Action Items Section -->
                        <div v-if="recording.ai_action_items?.length" class="p-5 sm:p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5 text-[#22C55E]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                    </svg>
                                    <h2 class="font-semibold text-[#0F172A]">{{ t('actionItems.title') }}</h2>
                                </div>
                                <span class="text-sm text-[#64748B]">{{ recording.ai_action_items.length }}</span>
                            </div>
                            <ul class="space-y-3">
                                <li v-for="(item, index) in recording.ai_action_items" :key="index" class="flex items-start gap-3">
                                    <div class="w-5 h-5 mt-0.5 rounded border-2 border-[#E5E7EB] flex-shrink-0 cursor-pointer hover:border-[#22C55E] transition-colors"></div>
                                    <span class="text-[#334155]">{{ item }}</span>
                                </li>
                            </ul>
                        </div>
                    </article>
                </template>

                <!-- Uploaded or Failed State -->
                <template v-else>
                    <div class="text-center py-12">
                        <div
                            class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
                            :class="isFailed ? 'bg-red-100' : 'bg-blue-100'"
                        >
                            <svg v-if="isFailed" class="w-8 h-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <svg v-else class="w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                        </div>

                        <h2 class="text-xl font-semibold text-[#0F172A] mb-2">
                            {{ isFailed ? t('show.failed') : t('show.uploaded') }}
                        </h2>
                        <p class="text-[#64748B] mb-6">
                            {{ isFailed ? t('show.failedDesc') : t('show.uploadedDesc') }}
                        </p>

                        <!-- Recording info -->
                        <div class="inline-flex items-center gap-3 px-4 py-2 bg-[#F8FAFC] rounded-lg text-sm text-[#64748B] mb-6">
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ formatDuration(recording.duration_seconds) }}
                            </span>
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', currentStatus.class]">
                                {{ currentStatus.label }}
                            </span>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <button
                                v-if="canProcess"
                                @click="startProcessing"
                                class="px-5 py-2.5 text-sm font-medium text-white bg-[#4F46E5] rounded-lg hover:bg-[#4338CA] transition-colors"
                            >
                                {{ isFailed ? t('show.retryProcessing') : t('show.processWithAI') }}
                            </button>
                        </div>
                    </div>
                </template>
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

                                <!-- Loading state -->
                                <div v-if="isSharing" class="flex items-center justify-center py-8">
                                    <svg class="w-8 h-8 animate-spin text-[#4F46E5]" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                </div>

                                <!-- Share URL ready -->
                                <div v-else-if="shareUrl" class="space-y-4">
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
                                            class="px-4 py-2.5 text-sm font-medium rounded-lg transition-colors flex items-center gap-1.5"
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

                                    <!-- Native share (if available) -->
                                    <button
                                        v-if="'share' in navigator"
                                        @click="nativeShare"
                                        class="w-full px-4 py-3 text-sm font-medium text-[#334155] bg-[#F8FAFC] hover:bg-[#EEF2FF] border border-[#E5E7EB] rounded-lg transition-colors flex items-center justify-center gap-2"
                                    >
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                        </svg>
                                        {{ t('share.moreOptions') }}
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
