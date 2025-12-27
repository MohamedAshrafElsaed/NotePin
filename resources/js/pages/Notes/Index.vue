<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useTrans } from '@/composables/useTrans';
import { useAuth } from '@/composables/useAuth';
import AppLayout from '@/layouts/AppLayout.vue';

const { t, locale } = useTrans();
const { user } = useAuth();

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
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return t('time.justNow');
    if (diffMins < 60) return t('time.minutesAgo', { count: diffMins });
    if (diffHours < 24) return t('time.hoursAgo', { count: diffHours });
    if (diffDays === 1) return t('time.yesterday');
    if (diffDays < 7) return t('time.daysAgo', { count: diffDays });

    return date.toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        month: 'short',
        day: 'numeric'
    });
};

const statusConfig = computed(() => ({
    uploaded: { label: t('status.uploaded'), class: 'bg-blue-100 text-blue-700' },
    processing: { label: t('status.processing'), class: 'bg-yellow-100 text-yellow-700' },
    ready: { label: t('status.ready'), class: 'bg-green-100 text-green-700' },
    failed: { label: t('status.failed'), class: 'bg-red-100 text-red-700' },
}));

const handleLogout = () => {
    router.post('/logout');
};

const getInitials = (name: string | null | undefined, email: string | null | undefined): string => {
    if (name) return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
    if (email) return email[0].toUpperCase();
    return '?';
};
</script>

<template>
    <Head :title="t('notes.title')" />

    <AppLayout :show-fab="true">
        <template #header>
            <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-sm border-b border-[#E5E7EB]">
                <div class="px-4 sm:px-6">
                    <div class="flex items-center justify-between h-14">
                        <!-- Logo -->
                        <Link href="/" class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-[#4F46E5] rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </div>
                            <span class="text-lg font-semibold text-[#0F172A]">{{ t('app.name') }}</span>
                        </Link>

                        <!-- User menu -->
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full overflow-hidden bg-[#4F46E5] flex items-center justify-center">
                                    <img
                                        v-if="user?.avatar"
                                        :src="user.avatar"
                                        :alt="user.name || ''"
                                        class="w-full h-full object-cover"
                                    />
                                    <span v-else class="text-white text-sm font-medium">
                                        {{ getInitials(user?.name, user?.email) }}
                                    </span>
                                </div>
                            </div>
                            <button
                                @click="handleLogout"
                                class="text-sm text-[#64748B] hover:text-[#0F172A] transition-colors"
                            >
                                {{ t('auth.signOut') }}
                            </button>
                        </div>
                    </div>
                </div>
            </header>
        </template>

        <div class="flex-1 px-4 sm:px-6 py-6">
            <!-- Page title -->
            <div class="mb-6">
                <h1 class="text-xl font-semibold text-[#0F172A]">{{ t('notes.title') }}</h1>
                <p class="text-sm text-[#64748B]">{{ notes.length }} {{ t('notes.recordings') }}</p>
            </div>

            <!-- Search -->
            <div v-if="notes.length > 3" class="relative mb-6">
                <svg class="absolute start-3 top-1/2 -translate-y-1/2 w-5 h-5 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="t('notes.searchPlaceholder')"
                    class="w-full ps-10 pe-4 py-2.5 bg-white border border-[#E5E7EB] rounded-xl text-[#0F172A] placeholder-[#64748B] focus:outline-none focus:border-[#4F46E5] focus:ring-2 focus:ring-[#EEF2FF] transition-colors text-sm"
                />
            </div>

            <!-- Notes List -->
            <div v-if="filteredNotes.length" class="space-y-3">
                <Link
                    v-for="note in filteredNotes"
                    :key="note.id"
                    :href="`/notes/${note.id}`"
                    class="block bg-white border border-[#E5E7EB] rounded-xl p-4 hover:border-[#4F46E5]/30 hover:shadow-sm transition-all active:scale-[0.99]"
                >
                    <div class="flex items-start gap-3">
                        <!-- Icon -->
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                             :class="note.status === 'ready' ? 'bg-[#F0FDF4]' : 'bg-[#EEF2FF]'">
                            <svg v-if="note.status === 'ready'" class="w-5 h-5 text-[#22C55E]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <svg v-else-if="note.status === 'processing'" class="w-5 h-5 text-[#F59E0B] animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            <svg v-else class="w-5 h-5 text-[#4F46E5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                            </svg>
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2 mb-1">
                                <h3 class="font-medium text-[#0F172A] truncate">
                                    {{ note.title || t('notes.untitled') }}
                                </h3>
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-medium flex-shrink-0"
                                    :class="statusConfig[note.status as keyof typeof statusConfig]?.class || 'bg-gray-100 text-gray-700'"
                                >
                                    {{ statusConfig[note.status as keyof typeof statusConfig]?.label || note.status }}
                                </span>
                            </div>
                            <p v-if="note.summary" class="text-sm text-[#64748B] line-clamp-2 mb-2">
                                {{ note.summary }}
                            </p>
                            <div class="flex items-center gap-3 text-xs text-[#64748B]">
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
                                    {{ note.actionItemsCount }}
                                </span>
                                <span>{{ formatDate(note.createdAt) }}</span>
                            </div>
                        </div>

                        <!-- Arrow -->
                        <svg class="w-5 h-5 text-[#D1D5DB] flex-shrink-0 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </Link>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-16">
                <div class="w-16 h-16 bg-[#EEF2FF] rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-[#4F46E5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-[#0F172A] mb-2">
                    {{ searchQuery ? t('notes.noResults') : t('notes.noNotes') }}
                </h3>
                <p class="text-[#64748B] max-w-xs mx-auto mb-6">
                    {{ searchQuery ? t('notes.noResultsDesc') : t('notes.noNotesDesc') }}
                </p>

                <!-- Visual hint to FAB -->
                <div v-if="!searchQuery" class="flex flex-col items-center text-[#64748B]">
                    <svg class="w-6 h-6 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                    </svg>
                    <span class="text-sm mt-2">{{ t('notes.tapMicHint') }}</span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
