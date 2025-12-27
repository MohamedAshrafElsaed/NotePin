<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';

const { t, locale } = useTrans();

interface Note {
    id: number;
    title: string;
    summary: string;
    actionItemsCount: number;
    completedCount: number;
    duration: string;
    createdAt: string;
}

const props = defineProps<{
    notes?: Note[];
}>();

const searchQuery = ref('');

const notes = ref<Note[]>(props.notes ?? [
    {
        id: 1,
        title: 'Q1 Product Roadmap Planning',
        summary: 'Discussed Q1 priorities: onboarding improvements, mobile app beta, and Slack integration.',
        actionItemsCount: 5,
        completedCount: 2,
        duration: '47:32',
        createdAt: '2025-01-15T10:30:00Z',
    },
    {
        id: 2,
        title: 'Sales Team Weekly Sync',
        summary: 'Reviewed pipeline status, discussed enterprise deals in progress, and upcoming marketing campaigns.',
        actionItemsCount: 3,
        completedCount: 1,
        duration: '32:15',
        createdAt: '2025-01-14T14:00:00Z',
    },
    {
        id: 3,
        title: 'Customer Feedback Review',
        summary: 'Analyzed NPS scores and common feature requests. Prioritized top 3 issues for immediate action.',
        actionItemsCount: 4,
        completedCount: 4,
        duration: '28:45',
        createdAt: '2025-01-12T11:00:00Z',
    },
    {
        id: 4,
        title: 'Engineering Sprint Planning',
        summary: 'Scoped next sprint work, addressed technical debt items, and reviewed deployment schedule.',
        actionItemsCount: 6,
        completedCount: 0,
        duration: '55:20',
        createdAt: '2025-01-10T09:00:00Z',
    },
]);

const filteredNotes = computed(() => {
    if (!searchQuery.value) return notes.value;
    const q = searchQuery.value.toLowerCase();
    return notes.value.filter(n =>
        n.title.toLowerCase().includes(q) ||
        n.summary.toLowerCase().includes(q)
    );
});

const formatDate = (dateStr: string) => {
    const date = new Date(dateStr);
    const now = new Date();
    const diffDays = Math.floor((now.getTime() - date.getTime()) / (1000 * 60 * 60 * 24));

    if (diffDays === 0) return t('time.today');
    if (diffDays === 1) return t('time.yesterday');
    if (diffDays < 7) return t('time.daysAgo', { count: diffDays });

    return date.toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        month: 'short',
        day: 'numeric'
    });
};
</script>

<template>
    <Head :title="t('notes.title')" />

    <AppLayout>
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-semibold text-[#0F172A]">{{ t('notes.title') }}</h1>
                    <p class="text-[#64748B] mt-1">{{ notes.length }} {{ t('notes.recordings') }}</p>
                </div>
                <Link
                    href="/"
                    class="inline-flex items-center gap-2 bg-[#4F46E5] hover:bg-[#4338CA] text-white px-4 py-2.5 rounded-lg font-medium transition-colors"
                >
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <circle cx="10" cy="10" r="4" />
                    </svg>
                    {{ t('nav.newRecording') }}
                </Link>
            </div>

            <!-- Search -->
            <div class="relative mb-6">
                <svg class="absolute start-4 top-1/2 -translate-y-1/2 w-5 h-5 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="t('notes.searchPlaceholder')"
                    class="w-full ps-12 pe-4 py-3 bg-white border border-[#E5E7EB] rounded-xl text-[#0F172A] placeholder-[#64748B] focus:outline-none focus:border-[#4F46E5] focus:ring-2 focus:ring-[#EEF2FF] transition-colors"
                />
            </div>

            <!-- Notes List -->
            <div v-if="filteredNotes.length" class="space-y-3">
                <Link
                    v-for="note in filteredNotes"
                    :key="note.id"
                    :href="`/notes/${note.id}`"
                    class="block bg-white border border-[#E5E7EB] rounded-xl p-5 hover:border-[#4F46E5]/30 hover:shadow-sm transition-all group"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-[#0F172A] group-hover:text-[#4F46E5] transition-colors truncate">
                                {{ note.title }}
                            </h3>
                            <p class="text-sm text-[#64748B] mt-1 line-clamp-2">
                                {{ note.summary }}
                            </p>
                            <div class="flex items-center gap-4 mt-3 text-xs text-[#64748B]">
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ note.duration }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                    </svg>
                                    {{ note.completedCount }}/{{ note.actionItemsCount }} {{ t('notes.tasks') }}
                                </span>
                                <span>{{ formatDate(note.createdAt) }}</span>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div
                                :class="[
                                    'w-10 h-10 rounded-full flex items-center justify-center text-xs font-medium',
                                    note.completedCount === note.actionItemsCount
                                        ? 'bg-[#F0FDF4] text-[#22C55E]'
                                        : 'bg-[#F8FAFC] text-[#64748B]'
                                ]"
                            >
                                <span v-if="note.completedCount === note.actionItemsCount">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                <span v-else>{{ Math.round((note.completedCount / note.actionItemsCount) * 100) }}%</span>
                            </div>
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-16">
                <div class="w-16 h-16 bg-[#F8FAFC] rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-[#0F172A] mb-2">
                    {{ searchQuery ? t('notes.noResults') : t('notes.noNotes') }}
                </h3>
                <p class="text-[#64748B] mb-6">
                    {{ searchQuery ? t('notes.noResultsDesc') : t('notes.noNotesDesc') }}
                </p>
                <Link
                    v-if="!searchQuery"
                    href="/"
                    class="inline-flex items-center gap-2 bg-[#4F46E5] hover:bg-[#4338CA] text-white px-5 py-2.5 rounded-lg font-medium transition-colors"
                >
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <circle cx="10" cy="10" r="4" />
                    </svg>
                    {{ t('notes.startRecording') }}
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
