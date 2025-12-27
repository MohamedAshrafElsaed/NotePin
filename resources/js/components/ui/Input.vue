<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    modelValue?: string | number;
    type?: 'text' | 'email' | 'password' | 'search' | 'number' | 'tel' | 'url';
    placeholder?: string;
    disabled?: boolean;
    error?: string;
    label?: string;
    id?: string;
}

const props = withDefaults(defineProps<Props>(), {
    type: 'text',
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string | number];
}>();

const inputId = computed(() => props.id || `input-${Math.random().toString(36).slice(2, 9)}`);

const handleInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    emit('update:modelValue', target.value);
};
</script>

<template>
    <div class="w-full">
        <label
            v-if="label"
            :for="inputId"
            class="block text-sm font-medium text-[#0F172A] mb-1.5"
        >
            {{ label }}
        </label>
        <div class="relative">
            <slot name="prefix" />
            <input
                :id="inputId"
                :type="type"
                :value="modelValue"
                :placeholder="placeholder"
                :disabled="disabled"
                :class="[
                    'w-full px-4 py-3 bg-white border rounded-xl text-[#0F172A] placeholder-[#64748B] transition-colors',
                    'focus:outline-none focus:ring-2 focus:ring-[#EEF2FF]',
                    error
                        ? 'border-[#EF4444] focus:border-[#EF4444]'
                        : 'border-[#E5E7EB] focus:border-[#4F46E5]',
                    disabled && 'bg-[#F8FAFC] cursor-not-allowed opacity-60',
                    $slots.prefix && 'pl-11',
                    $slots.suffix && 'pr-11',
                ]"
                @input="handleInput"
            />
            <slot name="suffix" />
        </div>
        <p v-if="error" class="mt-1.5 text-sm text-[#EF4444]">
            {{ error }}
        </p>
    </div>
</template>
