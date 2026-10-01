<script setup lang="ts">
import AppIcon from './AppIcon.vue';

/** compact: name hidden on phones. iconOnly: name hidden at every size (still read by screen readers). */
withDefaults(defineProps<{ compact?: boolean; iconOnly?: boolean; subtitle?: string }>(), {
    compact: false,
    iconOnly: false,
});

const appName = import.meta.env.VITE_APP_NAME;
</script>

<template>
    <div class="flex min-w-0 items-center gap-2.5 text-base font-bold text-fg sm:text-lg">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-primary text-on-primary">
            <AppIcon name="bag" />
        </span>
        <span v-if="iconOnly" class="sr-only">{{ appName }}</span>
        <span v-else-if="subtitle" class="flex min-w-0 flex-col leading-tight">
            <span class="truncate text-base">{{ appName }}</span>
            <span class="truncate text-xs font-medium text-fg-muted">{{ subtitle }}</span>
        </span>
        <!-- compact: on phones only the logo shows, leaving room for a badge. -->
        <span v-else :class="['truncate', { 'sr-only sm:not-sr-only': compact }]">{{ appName }}</span>
        <slot />
    </div>
</template>
