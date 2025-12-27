<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useTrans } from '@/composables/useTrans';
import { useAuth } from '@/composables/useAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthModal from '@/components/AuthModal.vue';

const { t, locale } = useTrans();
const { isAuthenticated, user, recordingCount } = useAuth();

interface PageProps {
    auth: {
        user: { id: number; name: string | null; email: string | null; avatar: string | null } | null;
        isAuthenticated: boolean;
        recordingCount: number;
    };
}

const page = usePage<PageProps>();
const showAuthModal = ref(false);

const hasRecordings = computed(() => recordingCount.value > 0);

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

const getInitials = (name: string | null | undefined, email: string | null | undefined): string => {
    if (name) return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
    if (email) return email[0].toUpperCase();
    return '?';
};
</script>

<template>
    <Head :title="t('app.name')" />

    <AppLayout :show-fab="true">
        <template #header>
            <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-sm border-b border-[#E5E7EB]">
                <div class="px-4 sm:px-6">
                    <div class="flex items-center justify-between h-14">
                        <!-- Logo -->
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-[#4F46E5] rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </div>
                            <span class="text-lg font-semibold text-[#0F172A]">{{ t('app.name') }}</span>
                        </div>

                        <!-- Auth -->
                        <div class="flex items-center gap-2">
                            <button
                                v-if="!isAuthenticated"
                                @click="showAuthModal = true"
                                class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-[#4F46E5] hover:bg-[#EEF2FF] rounded-lg transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                {{ t('auth.signIn') }}
                            </button>

                            <Link
                                v-if="isAuthenticated"
                                href="/dashboard"
                                class="flex items-center justify-center w-9 h-9 rounded-full overflow-hidden border-2 border-transparent hover:border-[#4F46E5] transition-colors"
                            >
                                <img
                                    v-if="user?.avatar"
                                    :src="user.avatar"
                                    :alt="user.name || ''"
                                    class="w-full h-full object-cover"
                                />
                                <div
                                    v-else
                                    class="w-full h-full bg-[#4F46E5] flex items-center justify-center text-white text-sm font-medium"
                                >
                                    {{ getInitials(user?.name, user?.email) }}
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>
            </header>
        </template>

        <div class="flex-1 px-4 sm:px-6 py-6">
            <!-- Hero Section -->
            <div class="text-center mb-8 pt-4">
                <h1 class="text-2xl sm:text-3xl font-semibold text-[#0F172A] mb-2">{{ t('app.tagline') }}</h1>
                <p class="text-[#64748B]">{{ t('app.description') }}</p>
            </div>

            <!-- Notes Section -->
            <div v-if="isAuthenticated && hasRecordings" class="mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[#0F172A]">{{ t('notes.recentNotes') }}</h2>
                    <Link href="/dashboard" class="text-sm font-medium text-[#4F46E5] hover:text-[#4338CA]">
                        {{ t('notes.viewAll') }}
                    </Link>
                </div>

                <!-- Placeholder for notes - user should navigate to dashboard -->
                <div class="bg-white border border-[#E5E7EB] rounded-xl p-4">
                    <Link href="/dashboard" class="flex items-center gap-3 text-[#334155] hover:text-[#4F46E5] transition-colors">
                        <div class="w-10 h-10 bg-[#EEF2FF] rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-[#4F46E5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium">{{ t('notes.youHaveNotes', { count: recordingCount }) }}</p>
                            <p class="text-sm text-[#64748B]">{{ t('notes.tapToView') }}</p>
                        </div>
                        <svg class="w-5 h-5 text-[#64748B] rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-12">
                <div class="w-20 h-20 bg-[#EEF2FF] rounded-2xl flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-[#4F46E5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-[#0F172A] mb-2">{{ t('notes.emptyTitle') }}</h3>
                <p class="text-[#64748B] max-w-xs mx-auto mb-6">{{ t('notes.emptyDesc') }}</p>

                <!-- Visual hint to FAB -->
                <div class="flex flex-col items-center text-[#64748B]">
                    <svg class="w-6 h-6 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                    </svg>
                    <span class="text-sm mt-2">{{ t('notes.tapMicHint') }}</span>
                </div>
            </div>

            <!-- Features Section -->
            <div class="mt-8 pt-8 border-t border-[#E5E7EB]">
                <h3 class="text-sm font-semibold text-[#64748B] uppercase tracking-wide mb-4 text-center">{{ t('features.title') }}</h3>
                <div class="grid gap-4">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 bg-[#F0FDF4] rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-[#22C55E]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-[#0F172A]">{{ t('features.ai.title') }}</p>
                            <p class="text-sm text-[#64748B]">{{ t('features.ai.desc') }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 bg-[#EEF2FF] rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-[#4F46E5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-[#0F172A]">{{ t('features.actionItems.title') }}</p>
                            <p class="text-sm text-[#64748B]">{{ t('features.actionItems.desc') }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 bg-[#FEF3C7] rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-[#F59E0B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-[#0F172A]">{{ t('features.share.title') }}</p>
                            <p class="text-sm text-[#64748B]">{{ t('features.share.desc') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Auth Modal -->
        <AuthModal
            :show="showAuthModal"
            action="login"
            redirect-to="/dashboard"
            @close="showAuthModal = false"
        />
    </AppLayout>
</template>
