<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onUnmounted } from 'vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';

const { t, locale } = useTrans();

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

const page = usePage<{ recording: Recording }>();
const recording = ref<Recording>(page.props.recording);
const isProcessing = ref(false);
const pollInterval = ref<number | null>(null);

const shareUrl = ref<string | null>(null);
const isSharing = ref(false);
const copied = ref(false);

const canProcess = computed(() =>
    recording.value && ['uploaded', 'failed'].includes(recording.value.status)
);

const isReady = computed(() => recording.value?.status === 'ready');
const isFailed = computed(() => recording.value?.status === 'failed');
const isPolling = computed(() => recording.value?.status === 'processing' || isProcessing.value);

const errorMessage = computed(() => {
    if (isFailed.value && recording.value?.ai_meta?.error) {
        return t('show.failedDesc');
    }
    return null;
});

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
    const maxAttempts = 30;

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
        setTimeout(() => { copied.value = false; }, 1500);
    } catch {
        const input = document.querySelector<HTMLInputElement>('#share-url-input');
        if (input) {
            input.select();
            document.execCommand('copy');
            copied.value = true;
            setTimeout(() => { copied.value = false; }, 1500);
        }
    }
};

onUnmounted(() => { stopPolling(); });

const formatDuration = (seconds: number | null | undefined) => {
    if (!seconds) return '00:00';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
};

const formatDate = (dateStr: string | undefined) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const statusConfig = computed(() => ({
    uploaded: { label: t('status.uploaded'), class: 'bg-blue-100 text-blue-700' },
    processing: { label: t('status.processing'), class: 'bg-yellow-100 text-yellow-700' },
    ready: { label: t('status.ready'), class: 'bg-green-100 text-green-700' },
    failed: { label: t('status.failed'), class: 'bg-red-100 text-red-700' },
}));
</script>

<template>
    <Head :title="t('recording.title')" />

    <AppLayout>
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 text-sm text-[#64748B] mb-6">
                <Link href="/" class="hover:text-[#4F46E5] transition-colors">{{ t('nav.home') }}</Link>
                <svg class="w-4 h-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                <span class="text-[#334155]">{{ t('recording.title') }} #{{ recording?.id }}</span>
            </div>

            <template v-if="recording">
                <!-- Processing State -->
                <div v-if="isPolling" class="bg-white border border-[#E5E7EB] rounded-xl p-8 text-center">
                    <div class="w-16 h-16 bg-[#FEF3C7] rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-[#F59E0B] animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                    </div>
                    <h1 class="text-2xl font-semibold text-[#0F172A] mb-2">{{ t('show.processing') }}</h1>
                    <p class="text-[#64748B]">{{ t('show.processingDesc') }}</p>
                </div>

                <!-- Ready State -->
                <template v-else-if="isReady">
                    <!-- Title -->
                    <div class="mb-6">
                        <h1 class="text-2xl font-semibold text-[#0F172A]">{{ recording.ai_title || t('notes.untitled') }}</h1>
                        <div class="flex items-center gap-4 mt-2 text-sm text-[#64748B]">
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ formatDuration(recording.duration_seconds) }}
                            </span>
                            <span>{{ formatDate(recording.created_at) }}</span>
                        </div>
                    </div>

                    <!-- Share Section -->
                    <div class="bg-white border border-[#E5E7EB] rounded-xl p-4 mb-6">
                        <div v-if="!shareUrl" class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                </svg>
                                <span class="text-sm text-[#334155]">{{ t('share.title') }}</span>
                            </div>
                            <button @click="createShare" :disabled="isSharing" class="px-4 py-2 text-sm font-medium text-white bg-[#4F46E5] rounded-lg hover:bg-[#4338CA] transition-colors disabled:opacity-50">
                                {{ isSharing ? t('share.creating') : t('share.button') }}
                            </button>
                        </div>
                        <div v-else class="space-y-3">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-[#22C55E]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-sm text-[#334155]">{{ t('share.created') }}</span>
                            </div>
                            <div class="flex gap-2">
                                <input id="share-url-input" type="text" :value="shareUrl" readonly class="flex-1 px-3 py-2 text-sm bg-[#F8FAFC] border border-[#E5E7EB] rounded-lg text-[#334155]" />
                                <button @click="copyShareUrl" class="px-4 py-2 text-sm font-medium rounded-lg transition-colors" :class="copied ? 'bg-[#22C55E] text-white' : 'bg-[#4F46E5] text-white hover:bg-[#4338CA]'">
                                    {{ copied ? t('share.copied') : t('share.copy') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Card -->
                    <div class="bg-white border border-[#E5E7EB] rounded-xl p-6 mb-6">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-8 h-8 bg-[#EEF2FF] rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-[#4F46E5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <h2 class="text-lg font-semibold text-[#0F172A]">{{ t('summary.title') }}</h2>
                        </div>
                        <p class="text-[#334155] leading-relaxed whitespace-pre-line">{{ recording.ai_summary }}</p>
                    </div>

                    <!-- Action Items Card -->
                    <div v-if="recording.ai_action_items && recording.ai_action_items.length > 0" class="bg-white border border-[#E5E7EB] rounded-xl p-6 mb-6">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-8 h-8 bg-[#F0FDF4] rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-[#22C55E]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </div>
                            <h2 class="text-lg font-semibold text-[#0F172A]">{{ t('actionItems.title') }}</h2>
                        </div>
                        <ul class="space-y-3">
                            <li v-for="(item, index) in recording.ai_action_items" :key="index" class="flex items-start gap-3">
                                <div class="w-5 h-5 mt-0.5 rounded border-2 border-[#E5E7EB] flex-shrink-0"></div>
                                <span class="text-[#334155]">{{ item }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3">
                        <Link href="/" class="px-5 py-2.5 text-sm font-medium text-white bg-[#4F46E5] rounded-lg hover:bg-[#4338CA] transition-colors">
                            {{ t('recording.recordAnother') }}
                        </Link>
                    </div>
                </template>

                <!-- Uploaded or Failed State -->
                <template v-else>
                    <div class="bg-white border border-[#E5E7EB] rounded-xl p-8 text-center">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" :class="isFailed ? 'bg-[#FEF2F2]' : 'bg-[#F0FDF4]'">
                            <svg v-if="isFailed" class="w-8 h-8 text-[#EF4444]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <svg v-else class="w-8 h-8 text-[#22C55E]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>

                        <h1 class="text-2xl font-semibold text-[#0F172A] mb-2">
                            {{ isFailed ? t('show.failed') : t('show.uploaded') }}
                        </h1>
                        <p class="text-[#64748B] mb-6">
                            {{ isFailed ? errorMessage : t('show.uploadedDesc') }}
                        </p>

                        <!-- Recording Info -->
                        <div class="bg-[#F8FAFC] rounded-lg p-4 mb-6">
                            <div class="flex items-center justify-center gap-6 text-sm">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-[#334155]">{{ formatDuration(recording.duration_seconds) }}</span>
                                </div>
                                <span :class="['px-2 py-1 rounded-full text-xs font-medium', statusConfig[recording.status]?.class || 'bg-gray-100 text-gray-700']">
                                    {{ statusConfig[recording.status]?.label || recording.status }}
                                </span>
                            </div>
                            <p class="text-xs text-[#64748B] mt-2">{{ formatDate(recording.created_at) }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-center gap-3">
                            <button v-if="canProcess" @click="startProcessing" class="px-5 py-2.5 text-sm font-medium text-white bg-[#4F46E5] rounded-lg hover:bg-[#4338CA] transition-colors">
                                {{ isFailed ? t('show.retryProcessing') : t('show.processWithAI') }}
                            </button>
                            <Link href="/" class="px-5 py-2.5 text-sm font-medium text-[#334155] bg-white border border-[#E5E7EB] rounded-lg hover:bg-[#F8FAFC] transition-colors">
                                {{ t('recording.recordAnother') }}
                            </Link>
                        </div>
                    </div>
                </template>
            </template>

            <!-- Not Found -->
            <div v-else class="bg-white border border-[#E5E7EB] rounded-xl p-8 text-center">
                <div class="w-16 h-16 bg-[#FEF2F2] rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-[#EF4444]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-semibold text-[#0F172A] mb-2">{{ t('show.notFound') }}</h1>
                <p class="text-[#64748B] mb-6">{{ t('show.notFoundDesc') }}</p>
                <Link href="/" class="px-5 py-2.5 text-sm font-medium text-white bg-[#4F46E5] rounded-lg hover:bg-[#4338CA] transition-colors">
                    {{ t('show.goHome') }}
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
