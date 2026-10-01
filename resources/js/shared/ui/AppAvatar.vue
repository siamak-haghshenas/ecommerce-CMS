<script setup lang="ts">
import { computed } from 'vue';
import AppIcon from './AppIcon.vue';

const props = withDefaults(defineProps<{ name: string; size?: 'sm' | 'md' | 'lg'; anonymised?: boolean }>(), {
    size: 'md',
    anonymised: false,
});

const initial = computed(() => props.name.trim().charAt(0));
</script>

<template>
    <span
        aria-hidden="true"
        :class="[
            'flex shrink-0 items-center justify-center rounded-full font-bold',
            anonymised ? 'bg-surface-muted text-fg-muted' : 'bg-primary-soft text-fg',
            { 'size-9 text-sm': size === 'sm', 'size-10 text-base': size === 'md', 'size-16 text-2xl': size === 'lg' },
        ]"
    >
        <AppIcon v-if="anonymised" name="userX" :size="size === 'lg' ? 28 : 18" />
        <template v-else>{{ initial }}</template>
    </span>
</template>
