<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';

const { t } = useTrans();

const currentYear = new Date().getFullYear();

const footerLinks = [
    { key: 'footer.privacy', href: '#' },
    { key: 'footer.terms', href: '#' },
    { key: 'footer.support', href: '#' },
];
</script>

<template>
    <footer class="border-t border-[#E5E7EB] bg-white mt-auto">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <!-- Logo & Copyright -->
                <div class="flex items-center gap-2 text-[#64748B]">
                    <div class="w-6 h-6 bg-[#4F46E5] rounded-md flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </div>
                    <span class="text-sm">
                        © {{ currentYear }} {{ t('app.name') }}. {{ t('footer.allRightsReserved') }}
                    </span>
                </div>

                <!-- Footer Links -->
                <nav class="flex items-center gap-4 sm:gap-6">
                    <a
                        v-for="link in footerLinks"
                        :key="link.key"
                        :href="link.href"
                        class="text-sm text-[#64748B] hover:text-[#0F172A] transition-colors"
                    >
                        {{ t(link.key) }}
                    </a>
                </nav>
            </div>

            <!-- Optional: Social Links or Additional Info -->
            <div class="mt-6 pt-6 border-t border-[#E5E7EB] sm:hidden">
                <p class="text-xs text-center text-[#64748B]">
                    {{ t('app.description') }}
                </p>
            </div>
        </div>
    </footer>
</template>
