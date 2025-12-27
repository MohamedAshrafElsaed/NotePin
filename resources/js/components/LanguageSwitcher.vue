<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useTrans } from '@/composables/useTrans';

interface Language {
    code: string;
    name: string;
    nativeName: string;
    flag: string;
}

const { locale, locales } = useTrans();

const isOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

const languages: Record<string, Language> = {
    ar: { code: 'ar', name: 'Arabic', nativeName: 'العربية', flag: '🇸🇦' },
    en: { code: 'en', name: 'English', nativeName: 'English', flag: '🇺🇸' },
};

const currentLang = computed(() => languages[locale.value] || languages.ar);

const switchLocale = (newLocale: string) => {
    if (newLocale === locale.value) {
        isOpen.value = false;
        return;
    }

    router.post(`/locale/${newLocale}`, {}, {
        preserveState: false,
        preserveScroll: true,
        onSuccess: () => {
            isOpen.value = false;
        },
    });
};

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
};

const closeDropdown = () => {
    isOpen.value = false;
};

const handleClickOutside = (event: MouseEvent) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target as Node)) {
        closeDropdown();
    }
};

const handleEscape = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        closeDropdown();
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    document.addEventListener('keydown', handleEscape);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    document.removeEventListener('keydown', handleEscape);
});
</script>

<template>
    <div ref="dropdownRef" class="relative">
        <!-- Trigger Button -->
        <button
            @click.stop="toggleDropdown"
            class="flex items-center gap-1.5 sm:gap-2 px-2 sm:px-3 py-2 text-sm text-[#334155] hover:text-[#4F46E5] transition-colors rounded-lg hover:bg-[#F8FAFC] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#4F46E5] focus-visible:ring-offset-2"
            :aria-expanded="isOpen"
            aria-haspopup="listbox"
        >
            <span class="text-base leading-none">{{ currentLang.flag }}</span>
            <span class="hidden sm:inline font-medium">{{ currentLang.nativeName }}</span>
            <svg
                class="w-4 h-4 transition-transform duration-200"
                :class="{ 'rotate-180': isOpen }"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Dropdown Menu -->
        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-show="isOpen"
                class="absolute top-full mt-1 bg-white border border-[#E5E7EB] rounded-lg shadow-lg z-50 min-w-[150px] overflow-hidden end-0"
                role="listbox"
                :aria-label="'Select language'"
            >
                <button
                    v-for="loc in locales"
                    :key="loc"
                    @click="switchLocale(loc)"
                    class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-[#334155] hover:bg-[#F8FAFC] transition-colors focus:outline-none focus:bg-[#F8FAFC]"
                    :class="{ 'bg-[#EEF2FF] text-[#4F46E5]': locale === loc }"
                    role="option"
                    :aria-selected="locale === loc"
                >
                    <span class="text-base leading-none">{{ languages[loc]?.flag }}</span>
                    <span class="flex-1 text-start">{{ languages[loc]?.nativeName }}</span>
                    <svg
                        v-if="locale === loc"
                        class="w-4 h-4 text-[#4F46E5] flex-shrink-0"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </button>
            </div>
        </Transition>
    </div>
</template>
