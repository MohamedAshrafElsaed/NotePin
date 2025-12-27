<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useTrans } from '@/composables/useTrans';
import AppLayout from '@/layouts/AppLayout.vue';

const { t, locale } = useTrans();

interface Recording {
    id: number;
    ai_title: string | null;
    ai_summary: string | null;
    ai_action_items: string[] | null;
    duration_seconds: number | null;
    status: string;
    created_at: string;
}

interface PageProps {
    recordings: Recording[];
    auth: {
        user: {
            id: number;
            name: string | null;
            email: string | null;
            avatar: string | null;
        } | null;
    };
}

const page = usePage<PageProps>();
const recordings = computed(() => page.props.recordings || []);
const user = computed(() => page.props.auth?.user);

const searchQuery = ref('');

const filteredRecordings = computed(() => {
    if (!searchQuery.value) return recordings.value;
    const q = searchQuery.value.toLowerCase();
    return recordings.value.filter(r =>
        (r.ai_title?.toLowerCase().includes(q)) ||
        (r.ai_summary?.toLowerCase().includes(q))
    );
});

const formatDate = (dateStr: string) => {
    const date = new Date(dateStr);
    const now = new Date();
    const diffDays = Math.floor((now.getTime() - date.getTime()) / (1000 * 60 * 60 * 24));

    if (diffDays === 0) return t('time.today');
    if (diffDays === 1) return t('time.yesterday');
    if (diffDays < 7) return t('time.daysAgo', { count: diffDays });

    return date.toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', { month: 'short', day: 'numeric' });
};

const formatDuration = (seconds: number | null) => {
    if (!seconds) return '00:00';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
};

const getStatusConfig = (status: string) => {
    const config: Record<string, { label: string; class: string }> = {
        uploaded: { label: t('status.uploaded'), class: 'bg-blue-100 text-blue-700' },
        processing: { label: t('status.processing'), class: 'bg-yellow-100 text-yellow-700' },
        ready: { label: t('status.ready'), class: 'bg-green-100 text-green-700' },
        failed: { label: t('status.failed'), class: 'bg-red-100 text-red-700' },
    };
    return config[status] || { label: status, class: 'bg-gray-100 text-gray-700' };
};
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AppLayout :show-header="true" :show-footer="true">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-2xl font-semibold text-[#0F172A]">
                        {{ t('dashboard.welcome', { name: user?.name?.split(' ')[0] || '' }) }}
                    </h1>
                    <p class="text-[#64748B] mt-1">{{ recordings.length }} {{ t('notes.recordings') }}</p>
                </div>
                <Link
                    href="/"
                    class="inline-flex items-center justify-center gap-2 bg-[#4F46E5] hover:bg-[#4338CA] text-white px-5 py-2.5 rounded-lg font-medium transition-colors"
                >
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <circle cx="10" cy="10" r="4" />
                    </svg>
                    {{ t('nav.newRecording') }}
                </Link>
            </div>

            <!-- Search -->
            <div v-if="recordings.length > 0" class="relative mb-6">
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

            <!-- Recordings Grid -->
            <div v-if="filteredRecordings.length" class="grid gap-4 sm:grid-cols-2">
                <Link
                    v-for="recording in filteredRecordings"
                    :key="recording.id"
                    :href="`/notes/${recording.id}`"
                    class="bg-white border border-[#E5E7EB] rounded-xl p-5 hover:border-[#4F46E5]/30 hover:shadow-md transition-all group"
                >
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <h3 class="font-semibold text-[#0F172A] group-hover:text-[#4F46E5] transition-colors line-clamp-1">
                            {{ recording.ai_title || t('notes.untitled') }}
                        </h3>
                        <span :class="['px-2 py-0.5 rounded-full text-xs font-medium flex-shrink-0', getStatusConfig(recording.status).class]">
                            {{ getStatusConfig(recording.status).label }}
                        </span>
                    </div>
                    <p v-if="recording.ai_summary" class="text-sm text-[#64748B] line-clamp-2 mb-3">
                        {{ recording.ai_summary }}
                    </p>
                    <div class="flex items-center gap-4 text-xs text-[#64748B]">
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ formatDuration(recording.duration_seconds) }}
                        </span>
                        <span v-if="recording.ai_action_items?.length" class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            {{ recording.ai_action_items.length }} {{ t('notes.tasks') }}
                        </span>
                        <span>{{ formatDate(recording.created_at) }}</span>
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
