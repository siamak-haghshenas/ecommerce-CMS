<script setup lang="ts">
import AppIcon, { type IconName } from '@/shared/ui/AppIcon.vue';

defineProps<{
    label: string;
    value: string;
    icon: IconName;
    hint: string;
    trend?: { direction: 'up' | 'down' | 'same'; text: string };
}>();
</script>

<template>
    <article class="flex flex-col gap-4 rounded-2xl border border-line bg-surface p-5">
        <div class="flex items-start justify-between gap-3">
            <h3 class="text-sm font-semibold text-fg-muted">{{ label }}</h3>
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary">
                <AppIcon :name="icon" />
            </span>
        </div>
        <p class="text-3xl font-bold text-fg tabular-nums">{{ value }}</p>
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px]">
            <span class="text-fg-muted">{{ hint }}</span>
            <span
                v-if="trend"
                :class="[
                    'flex items-center gap-1 font-semibold',
                    {
                        'text-primary': trend.direction === 'up',
                        'text-danger': trend.direction === 'down',
                        'text-fg-muted': trend.direction === 'same',
                    },
                ]"
            >
                <AppIcon
                    v-if="trend.direction !== 'same'"
                    :name="trend.direction === 'up' ? 'trendUp' : 'trendDown'"
                    :size="16"
                />
                {{ trend.text }}
            </span>
        </div>
    </article>
</template>
