<script setup lang="ts">
import AppIcon, { type IconName } from './AppIcon.vue';

defineProps<{ icon: IconName; title: string; description?: string }>();
</script>

<template>
    <div class="flex flex-col items-center gap-3 px-5 py-12 text-center">
        <span class="flex size-12 items-center justify-center rounded-2xl bg-surface-muted text-fg-muted">
            <AppIcon :name="icon" :size="24" />
        </span>
        <p class="font-semibold text-fg">{{ title }}</p>
        <p v-if="description" class="max-w-sm text-sm leading-relaxed text-fg-muted">{{ description }}</p>
        <slot />
    </div>
</template>
