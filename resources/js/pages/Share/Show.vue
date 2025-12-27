<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTrans } from '@/composables/useTrans';

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

const formatDate = (dateStr: string | undefined) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
};

const formatDuration = (seconds: number | null | undefined) => {
    if (!seconds) return null;
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
};
</script>

<template>
    <Head :title="`${recording?.ai_title || t('share.sharedNote')} - ${t('app.name')}`" />

    <div class="min-h-screen bg-[#F8FAFC] flex flex-col" :dir="isRTL ? 'rtl' : 'ltr'">
        <!-- Header -->
        <header class="bg-white border-b border-[#E5E7EB]">
            <div class="px-4 sm:px-6 max-w-2xl mx-auto">
                <div class="flex items-center justify-between h-14">
                    <!-- Logo -->
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-[#4F46E5] rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                        </div>
                        <span class="text-lg font-semibold text-[#0F172A]">{{ t('app.name') }}</span>
                    </div>

                    <!-- Shared badge -->
                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-[#EEF2FF] text-[#4F46E5] text-xs font-medium rounded-full">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                        </svg>
                        {{ t('share.sharedNote') }}
                    </span>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main class="flex-1 px-4 sm:px-6 py-6 max-w-2xl mx-auto w-full">
            <template v-if="recording">
                <!-- Note Card -->
                <article class="bg-white border border-[#E5E7EB] rounded-2xl overflow-hidden mb-6">
                    <!-- Note Header -->
                    <div class="p-5 sm:p-6 border-b border-[#E5E7EB]">
                        <h1 class="text-xl sm:text-2xl font-semibold text-[#0F172A] mb-3">
                            {{ recording.ai_title || t('notes.untitled') }}
                        </h1>
                        <div class="flex flex-wrap items-center gap-3 text-sm text-[#64748B]">
                            <span v-if="formatDuration(recording.duration_seconds)" class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ formatDuration(recording.duration_seconds) }}
                            </span>
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ formatDate(recording.created_at) }}
                            </span>
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
                            <span class="text-sm text-[#64748B]">{{ recording.ai_action_items.length }} {{ t('actionItems.items') }}</span>
                        </div>
                        <ul class="space-y-3">
                            <li v-for="(item, index) in recording.ai_action_items" :key="index" class="flex items-start gap-3">
                                <div class="w-5 h-5 mt-0.5 rounded border-2 border-[#E5E7EB] flex-shrink-0"></div>
                                <span class="text-[#334155]">{{ item }}</span>
                            </li>
                        </ul>
                    </div>
                </article>

                <!-- CTA -->
                <div class="bg-[#4F46E5] rounded-2xl p-6 text-center text-white">
                    <h3 class="text-lg font-semibold mb-2">{{ t('cta.title') }}</h3>
                    <p class="text-white/80 text-sm mb-4 max-w-xs mx-auto">{{ t('cta.description') }}</p>
                    <Link
                        href="/"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-white text-[#4F46E5] font-medium rounded-lg hover:bg-gray-50 transition-colors text-sm"
                    >
                        {{ t('cta.button') }}
                        <svg class="w-4 h-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </Link>
                </div>
            </template>

            <!-- Not Found -->
            <div v-else class="text-center py-16">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-[#0F172A] mb-2">{{ t('share.noteNotFound') }}</h2>
                <p class="text-[#64748B] mb-6">{{ t('share.noteNotFoundDesc') }}</p>
                <Link href="/" class="px-5 py-2.5 text-sm font-medium text-white bg-[#4F46E5] rounded-lg hover:bg-[#4338CA] transition-colors inline-block">
                    {{ t('share.goToApp') }}
                </Link>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-[#E5E7EB] bg-white mt-auto">
            <div class="px-4 sm:px-6 py-4 max-w-2xl mx-auto">
                <div class="flex items-center justify-center gap-2 text-sm text-[#64748B]">
                    <div class="w-5 h-5 bg-[#4F46E5] rounded flex items-center justify-center">
                        <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </div>
                    <span>{{ t('footer.poweredBy') }}</span>
                </div>
            </div>
        </footer>
    </div>
</template>
