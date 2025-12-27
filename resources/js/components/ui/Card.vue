<script setup lang="ts">
interface Props {
    padding?: 'none' | 'sm' | 'md' | 'lg';
    hover?: boolean;
}

withDefaults(defineProps<Props>(), {
    padding: 'md',
    hover: false,
});
</script>

<template>
    <div
        :class="[
            'bg-white border border-[#E5E7EB] rounded-xl',
            {
                'p-0': padding === 'none',
                'p-4': padding === 'sm',
                'p-6': padding === 'md',
                'p-8': padding === 'lg',
            },
            hover && 'hover:border-[#4F46E5]/30 hover:shadow-sm transition-all cursor-pointer',
        ]"
    >
        <slot />
    </div>
</template>
