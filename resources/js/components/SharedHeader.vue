<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useTrans } from '@/composables/useTrans';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';

const { t } = useTrans();
</script>

<template>
    <header class="bg-white border-b border-[#E5E7EB]">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
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

                <!-- Actions -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <LanguageSwitcher />
                    <Link
                        href="/"
                        class="text-sm font-medium text-[#4F46E5] hover:text-[#4338CA] transition-colors flex items-center gap-1"
                    >
                        <span class="hidden sm:inline">{{ t('nav.tryFree') }}</span>
                        <span class="sm:hidden">{{ t('cta.button') }}</span>
                        <svg class="w-4 h-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </Link>
                </div>
            </div>
        </div>
    </header>
</template>
