<script setup lang="ts">
import { computed } from 'vue';
import type { SeoRating } from '../analysis';

/** A traffic-light dot. The label is read by screen readers (and shown as a tooltip). */
const props = withDefaults(defineProps<{ rating: SeoRating | null; label: string; size?: 'sm' | 'md' }>(), {
    size: 'md',
});

const COLORS: Record<SeoRating, string> = { good: 'bg-success', ok: 'bg-warning', bad: 'bg-danger' };

/** Not analysed yet: an empty ring. */
const color = computed(() => (props.rating ? COLORS[props.rating] : 'border-2 border-line-strong bg-transparent'));
</script>

<template>
    <span
        role="img"
        :aria-label="label"
        :title="label"
        :class="['inline-block shrink-0 rounded-full', size === 'sm' ? 'size-2.5' : 'size-3', color]"
    />
</template>
