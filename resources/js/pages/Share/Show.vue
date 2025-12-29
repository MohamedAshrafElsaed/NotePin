<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const { t, locale } = useTrans();

interface ActionItemFull {
    task: string;
    due_date: string | null;
    owner: string | null;
    project: string | null;
    confidence: 'low' | 'medium' | 'high';
}

interface Recording {
    id: number;
    ai_title: string | null;
    ai_summary: string | null;
    ai_action_items: string[] | null;
    ai_meta: {
        language?: string;
        decision_context?: string;
        action_items_full?: ActionItemFull[];
    } | null;
    duration_seconds: number | null;
    created_at: string;
}

const props = defineProps<{
    recording: Recording | null;
}>();

const hasRecording = computed(() => !!props.recording);

// Get full action items with metadata if available
const actionItemsFull = computed<ActionItemFull[]>(() => {
    if (!props.recording) return [];

    const full = props.recording.ai_meta?.action_items_full;
    if (full && Array.isArray(full)) return full;

    const simple = props.recording.ai_action_items;
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

const formatDate = (dateStr: string | undefined) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const formatDuration = (seconds: number | null | undefined) => {
    if (!seconds) return '';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
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
</script>

<template>
    <Head :title="recording?.ai_title || t('share.sharedNote')" />

    <div class="min-h-screen bg-[#F8FAFC]">
        <!-- Header -->
        <header class="border-b border-[#E5E7EB] bg-white">
            <div class="mx-auto max-w-2xl px-4 sm:px-6">
                <div class="flex h-14 items-center justify-between">
                    <Link href="/" class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#4F46E5]">
                            <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"
                                />
                            </svg>
                        </div>
                        <span class="font-semibold text-[#0F172A]">{{ t('app.name') }}</span>
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-2xl px-4 py-8 sm:px-6">
            <!-- Not Found -->
            <div v-if="!hasRecording" class="py-16 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
                    <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <h1 class="mb-2 text-xl font-semibold text-[#0F172A]">{{ t('share.noteNotFound') }}</h1>
                <p class="mb-6 text-[#64748B]">{{ t('share.noteNotFoundDesc') }}</p>
                <Link
                    href="/"
                    class="inline-flex items-center gap-2 rounded-lg bg-[#4F46E5] px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-[#4338CA]"
                >
                    {{ t('share.goToApp') }}
                </Link>
            </div>

            <!-- Recording Content -->
            <article v-else class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white">
                <!-- Title Section -->
                <div class="border-b border-[#E5E7EB] p-5 sm:p-6">
                    <div class="mb-3 flex items-center gap-2 text-sm text-[#64748B]">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                            />
                        </svg>
                        <span>{{ formatDate(recording.created_at) }}</span>
                        <template v-if="recording.duration_seconds">
                            <span>•</span>
                            <span>{{ formatDuration(recording.duration_seconds) }}</span>
                        </template>
                        <template v-if="recording.ai_meta?.language">
                            <span>•</span>
                            <span class="uppercase">{{ recording.ai_meta.language }}</span>
                        </template>
                    </div>
                    <h1 class="text-xl font-bold text-[#0F172A] sm:text-2xl">
                        {{ recording.ai_title || t('notes.untitled') }}
                    </h1>
                </div>

                <!-- Decision Context -->
                <div v-if="recording.ai_meta?.decision_context" class="px-5 pt-5 sm:px-6">
                    <div class="rounded-xl border border-[#C7D2FE] bg-[#EEF2FF] p-4">
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
                </div>

                <!-- Summary Section -->
                <div class="border-b border-[#E5E7EB] p-5 sm:p-6">
                    <div class="mb-3 flex items-center gap-2">
                        <svg class="h-5 w-5 text-[#4F46E5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            />
                        </svg>
                        <h2 class="font-semibold text-[#0F172A]">{{ t('summary.title') }}</h2>
                    </div>
                    <p class="leading-relaxed whitespace-pre-line text-[#334155]">{{ recording.ai_summary }}</p>
                </div>

                <!-- Action Items Section -->
                <div v-if="actionItemsFull.length" class="p-5 sm:p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-[#22C55E]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                                />
                            </svg>
                            <h2 class="font-semibold text-[#0F172A]">{{ t('actionItems.title') }}</h2>
                        </div>
                        <span class="text-sm text-[#64748B]">{{ actionItemsFull.length }} {{ t('actionItems.items') }}</span>
                    </div>
                    <ul class="space-y-4">
                        <li v-for="(item, index) in actionItemsFull" :key="index">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 h-5 w-5 flex-shrink-0 rounded border-2 border-[#E5E7EB]"></div>
                                <div class="flex-1">
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
                        </li>
                    </ul>
                </div>
            </article>

            <!-- CTA -->
            <div v-if="hasRecording" class="mt-8 rounded-2xl bg-[#4F46E5] p-6 text-center text-white">
                <h3 class="mb-2 text-lg font-semibold">{{ t('cta.title') }}</h3>
                <p class="mx-auto mb-4 max-w-xs text-sm text-white/80">{{ t('cta.description') }}</p>
                <Link
                    href="/"
                    class="inline-flex items-center gap-2 rounded-lg bg-white px-5 py-2.5 text-sm font-medium text-[#4F46E5] transition-colors hover:bg-gray-50"
                >
                    {{ t('cta.button') }}
                </Link>
            </div>
        </main>

        <!-- Footer -->
        <footer class="py-8 text-center text-sm text-[#64748B]">
            <p>{{ t('footer.poweredBy') }}</p>
        </footer>
    </div>
</template>
