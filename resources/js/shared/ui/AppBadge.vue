<script setup lang="ts">
export type BadgeTone = 'success' | 'neutral' | 'danger' | 'warning' | 'outline';

withDefaults(defineProps<{ tone?: BadgeTone }>(), { tone: 'neutral' });
</script>

<template>
    <span
        :class="[
            'inline-flex h-6 items-center rounded-full px-2.5 text-xs font-semibold whitespace-nowrap',
            {
                'bg-primary-soft text-fg': tone === 'success',
                'bg-surface-muted text-fg-muted': tone === 'neutral',
                'bg-danger-soft text-danger': tone === 'danger',
                'bg-warning-soft text-warning': tone === 'warning',
                'border border-dashed border-line-strong text-fg-muted': tone === 'outline',
            },
        ]"
    >
        <slot />
    </span>
</template>
