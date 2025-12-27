<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';

const { t } = useTrans();
</script>

<template>
    <footer class="border-t border-[#E5E7EB] bg-white mt-auto">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-center gap-4 text-sm text-[#64748B]">
                <div class="flex items-center gap-2">
                    <div class="w-5 h-5 bg-[#4F46E5] rounded flex items-center justify-center flex-shrink-0">
                        <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </div>
                    <span>{{ t('footer.poweredBy') }}</span>
                </div>
                <span class="text-[#E5E7EB]">•</span>
                <a
                    href="mailto:feedback@notepin.app"
                    class="text-[#4F46E5] hover:text-[#4338CA] font-medium transition-colors"
                >
                    {{ t('footer.feedback') }}
                </a>
            </div>
        </div>
    </footer>
</template>
