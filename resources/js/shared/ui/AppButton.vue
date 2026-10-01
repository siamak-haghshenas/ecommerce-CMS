<script setup lang="ts">
import AppSpinner from './AppSpinner.vue';

withDefaults(
    defineProps<{
        type?: 'button' | 'submit';
        variant?: 'primary' | 'secondary' | 'danger' | 'danger-outline';
        /** lg: full-width form button (52px). md: inline toolbar/dialog button (44px). */
        size?: 'lg' | 'md';
        loading?: boolean;
        disabled?: boolean;
    }>(),
    { type: 'button', variant: 'primary', size: 'lg', loading: false, disabled: false },
);
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        :aria-busy="loading"
        :class="[
            'inline-flex cursor-pointer items-center justify-center gap-2 font-semibold whitespace-nowrap transition-colors duration-150',
            'focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-bg focus-visible:outline-none',
            'disabled:cursor-not-allowed',
            size === 'lg' ? 'h-13 w-full gap-2.5 rounded-xl px-5 text-base' : 'h-11 rounded-[10px] px-4 text-sm',
            {
                'bg-primary text-on-primary hover:bg-primary-hover disabled:bg-disabled disabled:text-on-disabled':
                    variant === 'primary',
                'border border-line-strong bg-surface text-fg hover:bg-surface-muted disabled:text-on-disabled':
                    variant === 'secondary',
                'bg-danger text-on-danger hover:opacity-90 disabled:bg-disabled disabled:text-on-disabled':
                    variant === 'danger',
                'border border-danger-line bg-surface text-danger hover:bg-danger-soft disabled:text-on-disabled':
                    variant === 'danger-outline',
            },
            loading && variant === 'primary' && 'disabled:bg-primary disabled:text-on-primary',
            loading && variant === 'danger' && 'disabled:bg-danger disabled:text-on-danger',
        ]"
    >
        <AppSpinner v-if="loading" :size="size === 'lg' ? 20 : 18" />
        <slot />
    </button>
</template>
