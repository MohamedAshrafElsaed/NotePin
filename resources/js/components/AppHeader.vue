<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useTrans } from '@/composables/useTrans';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';

const { t } = useTrans();
</script>

<template>
    <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-sm border-b border-[#E5E7EB]">
        <nav class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <Link href="/" class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-[#4F46E5] rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </div>
                    <span class="text-xl font-semibold text-[#0F172A]">{{ t('app.name') }}</span>
                </Link>

                <!-- Nav Links -->
                <div class="flex items-center gap-2 sm:gap-4">
                    <Link
                        href="/notes"
                        class="text-[#334155] hover:text-[#0F172A] font-medium transition-colors px-3 py-2 rounded-lg hover:bg-[#F8FAFC]"
                    >
                        {{ t('nav.myNotes') }}
                    </Link>

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

                    <LanguageSwitcher />
                </div>
            </div>
        </nav>
    </header>
</template>
