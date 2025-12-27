<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';

const { t } = useTrans();

const emit = defineEmits<{
    click: [];
}>();
</script>

<template>
    <button
        @click="emit('click')"
        class="fab-mic"
        :aria-label="t('recording.record')"
    >
        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
        </svg>
    </button>
</template>

<style scoped>
.fab-mic {
    position: fixed;
    bottom: calc(24px + env(safe-area-inset-bottom, 0px));
    left: 50%;
    transform: translateX(-50%);
    z-index: 50;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background-color: #4F46E5;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
    transition: transform 0.15s ease, background-color 0.15s ease;
}

.fab-mic:hover {
    background-color: #4338CA;
}

.fab-mic:active {
    transform: translateX(-50%) scale(0.95);
}
</style>
