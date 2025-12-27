<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import { useTrans } from '@/composables/useTrans';
import { useAuth } from '@/composables/useAuth';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import AuthModal from '@/components/AuthModal.vue';

const { t } = useTrans();
const { user, isAuthenticated } = useAuth();

const showUserMenu = ref(false);
const showAuthModal = ref(false);
const userMenuRef = ref<HTMLElement | null>(null);

const toggleUserMenu = () => {
    showUserMenu.value = !showUserMenu.value;
};

const closeUserMenu = () => {
    showUserMenu.value = false;
};

const handleLogout = () => {
    closeUserMenu();
    router.post('/logout');
};

const handleClickOutside = (event: MouseEvent) => {
    if (userMenuRef.value && !userMenuRef.value.contains(event.target as Node)) {
        closeUserMenu();
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

const getInitials = (name: string | null | undefined, email: string | null | undefined): string => {
    if (name) {
        return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
    }
    if (email) {
        return email[0].toUpperCase();
    }
    return '?';
};
</script>

<template>
    <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-sm border-b border-[#E5E7EB]">
        <nav class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <Link :href="isAuthenticated ? '/dashboard' : '/'" class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-[#4F46E5] rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </div>
                    <span class="text-xl font-semibold text-[#0F172A]">{{ t('app.name') }}</span>
                    <span class="px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide bg-[#FEF3C7] text-[#B45309] rounded">Beta</span>
                </Link>

                <!-- Nav Links -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- My Notes link for authenticated users -->
                    <Link
                        v-if="isAuthenticated"
                        href="/dashboard"
                        class="text-[#334155] hover:text-[#0F172A] font-medium transition-colors px-3 py-2 rounded-lg hover:bg-[#F8FAFC]"
                    >
                        {{ t('nav.myNotes') }}
                    </Link>

                    <!-- New Recording button - desktop -->
                    <Link
                        href="/"
                        class="hidden sm:inline-flex items-center gap-2 bg-[#4F46E5] hover:bg-[#4338CA] text-white px-4 py-2 rounded-lg font-medium transition-colors"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <circle cx="10" cy="10" r="4" />
                        </svg>
                        {{ t('nav.newRecording') }}
                    </Link>

                    <!-- Mobile record button -->
                    <Link
                        href="/"
                        class="sm:hidden flex items-center justify-center w-10 h-10 bg-[#4F46E5] hover:bg-[#4338CA] text-white rounded-lg transition-colors"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <circle cx="10" cy="10" r="4" />
                        </svg>
                    </Link>

                    <!-- Language Switcher -->
                    <LanguageSwitcher />

                    <!-- Login button for non-authenticated users -->
                    <button
                        v-if="!isAuthenticated"
                        @click="showAuthModal = true"
                        class="flex items-center gap-2 px-3 sm:px-4 py-2 text-sm font-medium text-white bg-[#22C55E] hover:bg-[#16A34A] rounded-lg transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>{{ t('auth.signIn') }}</span>
                    </button>

                    <!-- User Menu for authenticated users -->
                    <div v-if="isAuthenticated && user" ref="userMenuRef" class="relative">
                        <button
                            @click.stop="toggleUserMenu"
                            class="flex items-center justify-center w-9 h-9 rounded-full overflow-hidden border-2 border-transparent hover:border-[#4F46E5] transition-colors focus:outline-none focus:border-[#4F46E5]"
                        >
                            <img
                                v-if="user.avatar"
                                :src="user.avatar"
                                :alt="user.name || user.email || 'User'"
                                class="w-full h-full object-cover"
                            />
                            <div
                                v-else
                                class="w-full h-full bg-[#4F46E5] flex items-center justify-center text-white text-sm font-medium"
                            >
                                {{ getInitials(user.name, user.email) }}
                            </div>
                        </button>

                        <Transition
                            enter-active-class="transition ease-out duration-100"
                            enter-from-class="transform opacity-0 scale-95"
                            enter-to-class="transform opacity-100 scale-100"
                            leave-active-class="transition ease-in duration-75"
                            leave-from-class="transform opacity-100 scale-100"
                            leave-to-class="transform opacity-0 scale-95"
                        >
                            <div
                                v-show="showUserMenu"
                                class="absolute top-full mt-1 end-0 w-48 bg-white border border-[#E5E7EB] rounded-lg shadow-lg z-50 overflow-hidden"
                            >
                                <div class="px-4 py-3 border-b border-[#E5E7EB]">
                                    <p class="text-sm font-medium text-[#0F172A] truncate">{{ user.name || 'User' }}</p>
                                    <p class="text-xs text-[#64748B] truncate">{{ user.email }}</p>
                                </div>
                                <button
                                    @click="handleLogout"
                                    class="w-full px-4 py-2.5 text-start text-sm text-[#334155] hover:bg-[#F8FAFC] transition-colors"
                                >
                                    {{ t('auth.signOut') }}
                                </button>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- Auth Modal -->
    <AuthModal
        :show="showAuthModal"
        action="login"
        redirect-to="/dashboard"
        @close="showAuthModal = false"
    />
</template>
