<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTrans } from '@/composables/useTrans';
import SharedHeader from '@/components/SharedHeader.vue';
import SharedFooter from '@/components/SharedFooter.vue';

const { t, locale, isRTL } = useTrans();

interface Recording {
    ai_title: string | null;
    ai_summary: string | null;
    ai_action_items: string[] | null;
    duration_seconds: number | null;
    created_at: string;
}

const page = usePage<{ recording: Recording }>();
const recording = computed(() => page.props.recording);
const totalCount = computed(() => recording.value?.ai_action_items?.length ?? 0);

const formatDate = (dateStr: string | undefined) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatDuration = (seconds: number | null | undefined) => {
    if (!seconds) return null;
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
};
</script>

<template>
    <Head :title="`${recording?.ai_title || t('share.sharedNote')} - ${t('share.sharedNote')}`" />

    <div class="shared-layout" :dir="isRTL ? 'rtl' : 'ltr'">
        <!-- Header -->
        <SharedHeader />

        <!-- Content -->
        <main class="shared-main">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <template v-if="recording">
                    <!-- Shared Badge -->
                    <div class="flex items-center gap-2 mb-6">
                        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#EEF2FF] text-[#4F46E5] text-sm font-medium rounded-full">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                            </svg>
                            {{ t('share.sharedNote') }}
                        </div>
                    </div>

                    <!-- Header -->
                    <div class="mb-8">
                        <h1 class="text-2xl sm:text-3xl font-semibold text-[#0F172A] mb-3">
                            {{ recording.ai_title || t('notes.untitled') }}
                        </h1>
                        <div class="flex flex-wrap items-center gap-4 text-sm text-[#64748B]">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ formatDate(recording.created_at) }}
                            </span>
                            <span v-if="formatDuration(recording.duration_seconds)" class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ formatDuration(recording.duration_seconds) }}
                            </span>
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
                    <div v-if="recording.ai_action_items && recording.ai_action_items.length > 0" class="bg-white border border-[#E5E7EB] rounded-xl p-6">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-[#F0FDF4] rounded-lg flex items-center justify-center">
                                    <svg class="w-4 h-4 text-[#22C55E]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                    </svg>
                                </div>
                                <h2 class="text-lg font-semibold text-[#0F172A]">{{ t('actionItems.title') }}</h2>
                            </div>
                            <span class="text-sm text-[#64748B]">{{ totalCount }} {{ t('actionItems.items') }}</span>
                        </div>

                        <ul class="space-y-3">
                            <li
                                v-for="(item, index) in recording.ai_action_items"
                                :key="index"
                                class="flex items-start gap-3 p-3 rounded-lg hover:bg-[#F8FAFC] transition-colors"
                            >
                                <div class="mt-0.5 flex-shrink-0">
                                    <div class="w-5 h-5 rounded border-2 border-[#E5E7EB]"></div>
                                </div>
                                <p class="text-[#0F172A] flex-1">{{ item }}</p>
                            </li>
                        </ul>
                    </div>

                    <!-- CTA Banner -->
                    <div class="mt-8 bg-gradient-to-r from-[#4F46E5] to-[#6366F1] rounded-xl p-6 sm:p-8 text-center text-white">
                        <h3 class="text-lg sm:text-xl font-semibold mb-2">{{ t('cta.title') }}</h3>
                        <p class="text-white/80 mb-4 sm:mb-6 max-w-md mx-auto">{{ t('cta.description') }}</p>
                        <Link
                            href="/"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-white text-[#4F46E5] font-medium rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            {{ t('cta.button') }}
                            <svg class="w-4 h-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </Link>
                    </div>
                </template>

                <!-- Not Found -->
                <div v-else class="bg-white border border-[#E5E7EB] rounded-xl p-8 text-center">
                    <div class="w-16 h-16 bg-[#FEF2F2] rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-[#EF4444]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h1 class="text-2xl font-semibold text-[#0F172A] mb-2">{{ t('share.noteNotFound') }}</h1>
                    <p class="text-[#64748B] mb-6">{{ t('share.noteNotFoundDesc') }}</p>
                    <Link
                        href="/"
                        class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-medium text-white bg-[#4F46E5] rounded-lg hover:bg-[#4338CA] transition-colors"
                    >
                        {{ t('share.goToApp') }}
                    </Link>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <SharedFooter />
    </div>
</template>

<style scoped>
.shared-layout {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    min-height: 100dvh;
    background-color: #F8FAFC;
}

.shared-main {
    flex: 1 1 auto;
}
</style>
