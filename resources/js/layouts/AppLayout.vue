<script setup lang="ts">
import { ref } from 'vue';
import { useTrans } from '@/composables/useTrans';
import FloatingMicButton from '@/components/FloatingMicButton.vue';
import RecorderSheet from '@/components/RecorderSheet.vue';

const { isRTL } = useTrans();

interface Props {
    showFab?: boolean;
    showHeader?: boolean;
}

withDefaults(defineProps<Props>(), {
    showFab: true,
    showHeader: true,
});

const showRecorder = ref(false);

const openRecorder = () => {
    showRecorder.value = true;
};
</script>

<template>
    <div class="app-layout" :dir="isRTL ? 'rtl' : 'ltr'">
        <!-- Header slot -->
        <slot name="header" />

        <!-- Main Content -->
        <main class="app-main" :class="{ 'has-fab': showFab }">
            <slot />
        </main>

        <!-- Floating Mic Button -->
        <FloatingMicButton v-if="showFab" @click="openRecorder" />

        <!-- Recorder Bottom Sheet -->
        <RecorderSheet :show="showRecorder" @close="showRecorder = false" />
    </div>
</template>

<style scoped>
.app-layout {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    min-height: 100dvh;
    background-color: #F8FAFC;
}

.app-main {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
}

.app-main.has-fab {
    padding-bottom: calc(96px + env(safe-area-inset-bottom, 0px));
}
</style>
