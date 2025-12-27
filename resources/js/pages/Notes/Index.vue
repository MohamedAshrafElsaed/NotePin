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
    status: string;
}

const props = defineProps<{
    notes?: Note[];
}>();

const searchQuery = ref('');
const notes = ref<Note[]>(props.notes ?? []);

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

const statusConfig: Record<string, { label: string; class: string }> = {
    uploaded: { label: t('status.uploaded'), class: 'bg-blue-100 text-blue-700' },
    processing: { label: t('status.processing'), class: 'bg-yellow-100 text-yellow-700' },
    ready: { label: t('status.ready'), class: 'bg-green-100 text-green-700' },
    failed: { label: t('status.failed'), class: 'bg-red-100 text-red-700' },
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
            <div v-if="notes.length > 0" class="relative mb-6">
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
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="font-semibold text-[#0F172A] group-hover:text-[#4F46E5] transition-colors truncate">
                                    {{ note.title || t('notes.untitled') }}
                                </h3>
                                <span :class="['px-2 py-0.5 rounded-full text-xs font-medium flex-shrink-0', statusConfig[note.status]?.class || 'bg-gray-100 text-gray-700']">
                                    {{ statusConfig[note.status]?.label || note.status }}
                                </span>
                            </div>
                            <p v-if="note.summary" class="text-sm text-[#64748B] mt-1 line-clamp-2">
                                {{ note.summary }}
                            </p>
                            <div class="flex items-center gap-4 mt-3 text-xs text-[#64748B]">
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ note.duration }}
                                </span>
                                <span v-if="note.actionItemsCount > 0" class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                    </svg>
                                    {{ note.actionItemsCount }} {{ t('notes.tasks') }}
                                </span>
                                <span>{{ formatDate(note.createdAt) }}</span>
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
