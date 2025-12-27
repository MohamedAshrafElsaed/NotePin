<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import AppHeader from '@/components/AppHeader.vue';
import AppFooter from '@/components/AppFooter.vue';

const { isRTL } = useTrans();

defineProps<{
    showHeader?: boolean;
    showFooter?: boolean;
}>();
</script>

<template>
    <div
        class="app-layout"
        :dir="isRTL ? 'rtl' : 'ltr'"
    >
        <!-- Header -->
        <AppHeader v-if="showHeader !== false" />

        <!-- Main Content -->
        <main class="app-main">
            <slot />
        </main>

        <!-- Footer (Sticky to bottom) -->
        <AppFooter v-if="showFooter !== false" />
    </div>
</template>

<style scoped>
.app-layout {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    min-height: 100dvh; /* Dynamic viewport height for mobile */
    background-color: #F8FAFC;
}

.app-main {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
}
</style>
