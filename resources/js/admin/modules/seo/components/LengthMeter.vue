<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';

/** A bar under a text field: green inside the recommended length, orange when short, red when too long. */
const props = defineProps<{ length: number; min: number; max: number }>();

const { t } = useI18n();
const { formatNumber } = useLocale();

const tone = computed(() =>
    props.length === 0 ? 'empty' : props.length > props.max ? 'bad' : props.length < props.min ? 'ok' : 'good',
);
/** The bar is full a little past the maximum, so "too long" is visible. */
const width = computed(() => `${Math.min(100, (props.length / (props.max * 1.25)) * 100)}%`);
</script>

<template>
    <div class="mt-2 flex items-center gap-3">
        <div class="h-1.5 grow overflow-hidden rounded-full bg-surface-muted" aria-hidden="true">
            <div
                :class="[
                    'h-full rounded-full transition-[width] duration-150',
                    { 'bg-success': tone === 'good', 'bg-warning': tone === 'ok', 'bg-danger': tone === 'bad' },
                ]"
                :style="{ width }"
            />
        </div>
        <span class="text-xs whitespace-nowrap text-fg-muted">
            {{ t('admin.seo.characters', { n: formatNumber(length), min: formatNumber(min), max: formatNumber(max) }) }}
        </span>
    </div>
</template>
