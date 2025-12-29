<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useTrans } from '@/composables/useTrans';
import { useAuth } from '@/composables/useAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthModal from '@/components/AuthModal.vue';

const { t } = useTrans();
const { isAuthenticated, user, recordingCount } = useAuth();

const showAuthModal = ref(false);

// Check if user has any notes (via recordingCount from shared props)
const hasNotes = computed(() => recordingCount.value > 0);

const getInitials = (name: string | null | undefined, email: string | null | undefined): string => {
    if (name) return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
    if (email) return email[0].toUpperCase();
    return '?';
};

const handleLogout = () => {
    router.post('/logout');
};
</script>

<template>
    <Head :title="t('app.name')" />

    <AppLayout :show-fab="true">
        <template #header>
            <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-sm border-b border-[#E5E7EB]">
                <div class="px-4 sm:px-6">
                    <div class="flex items-center justify-between h-14">
                        <!-- Logo with Beta badge -->
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-[#4F46E5] rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </div>
                            <span class="text-lg font-semibold text-[#0F172A]">{{ t('app.name') }}</span>
                            <span class="px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide bg-[#FEF3C7] text-[#B45309] rounded">Beta</span>
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

                            <!-- User menu for authenticated -->
                            <template v-if="isAuthenticated">
                                <Link
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
                            </template>
                        </div>
                    </div>
                </div>
            </header>
        </template>

        <div class="flex-1 px-4 sm:px-6">
            <!-- Authenticated user with recordings -->
            <template v-if="isAuthenticated && recordingCount > 0">
                <div class="py-6">
                    <h1 class="text-xl font-semibold text-[#0F172A] mb-1">{{ t('notes.myNotes') }}</h1>
                    <p class="text-sm text-[#64748B]">{{ recordingCount }} {{ t('notes.recordings') }}</p>
                </div>

                <!-- Notes card linking to dashboard -->
                <Link
                    href="/dashboard"
                    class="block bg-white border border-[#E5E7EB] rounded-xl p-4 hover:border-[#4F46E5]/30 hover:shadow-sm transition-all"
                >
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-[#EEF2FF] rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-[#4F46E5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-[#0F172A]">{{ t('notes.viewAll') }}</p>
                            <p class="text-sm text-[#64748B]">{{ t('notes.youHaveNotes', { count: recordingCount }) }}</p>
                        </div>
                        <svg class="w-5 h-5 text-[#64748B] rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </Link>

                <!-- Divider -->
                <div class="my-8 flex items-center gap-4">
                    <div class="flex-1 h-px bg-[#E5E7EB]"></div>
                    <span class="text-xs font-medium text-[#64748B] uppercase tracking-wider">{{ t('recording.recordAnother') }}</span>
                    <div class="flex-1 h-px bg-[#E5E7EB]"></div>
                </div>

                <!-- Mic hint -->
                <div class="text-center py-8">
                    <div class="w-16 h-16 bg-[#EEF2FF] rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-[#4F46E5]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                        </svg>
                    </div>
                    <p class="text-[#64748B]">{{ t('notes.tapMicHint') }}</p>
                </div>
            </template>

            <!-- Empty state: No recordings yet -->
            <template v-else>
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <!-- Large mic icon -->
                    <div class="relative mb-6">
                        <div class="w-24 h-24 bg-[#EEF2FF] rounded-full flex items-center justify-center">
                            <svg class="w-12 h-12 text-[#4F46E5]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                            </svg>
                        </div>
                        <!-- Decorative pulse ring -->
                        <div class="absolute inset-0 w-24 h-24 bg-[#4F46E5]/10 rounded-full animate-ping" style="animation-duration: 2s;"></div>
                    </div>

                    <h1 class="text-xl font-semibold text-[#0F172A] mb-2">{{ t('notes.emptyTitle') }}</h1>
                    <p class="text-[#64748B] mb-8 max-w-xs">{{ t('notes.emptyDesc') }}</p>

                    <!-- Arrow pointing down to FAB -->
                    <div class="flex flex-col items-center gap-2 text-[#64748B]">
                        <svg class="w-6 h-6 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                        </svg>
                        <span class="text-sm font-medium">{{ t('notes.tapMicHint') }}</span>
                    </div>
                </div>

                <!-- Features section for anonymous users -->
                <div v-if="!isAuthenticated" class="mt-8 space-y-4">
                    <h2 class="text-sm font-semibold text-[#64748B] uppercase tracking-wider text-center">{{ t('features.title') }}</h2>

                    <div class="grid gap-3">
                        <div class="flex items-start gap-3 bg-white border border-[#E5E7EB] rounded-xl p-4">
                            <div class="w-10 h-10 bg-[#EEF2FF] rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-[#4F46E5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-[#0F172A]">{{ t('features.ai.title') }}</p>
                                <p class="text-sm text-[#64748B]">{{ t('features.ai.desc') }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 bg-white border border-[#E5E7EB] rounded-xl p-4">
                            <div class="w-10 h-10 bg-[#F0FDF4] rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-[#22C55E]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-[#0F172A]">{{ t('features.actionItems.title') }}</p>
                                <p class="text-sm text-[#64748B]">{{ t('features.actionItems.desc') }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 bg-white border border-[#E5E7EB] rounded-xl p-4">
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
            </template>
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
