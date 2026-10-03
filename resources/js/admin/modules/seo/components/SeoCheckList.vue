<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import type { SeoCheck } from '../analysis';
import SeoScoreDot from './SeoScoreDot.vue';

/** The checks of one analysis, problems first. */
const props = defineProps<{ checks: SeoCheck[] }>();

const { t } = useI18n();
const { formatNumber } = useLocale();

const ORDER = { bad: 0, ok: 1, good: 2 } as const;

const items = computed(() =>
    [...props.checks]
        .sort((a, b) => ORDER[a.rating] - ORDER[b.rating])
        .map((check) => ({
            ...check,
            message: t(
                `admin.seo.checks.${check.id}.${check.code}`,
                Object.fromEntries(
                    Object.entries(check.params).map(([key, value]) => [
                        key,
                        typeof value === 'number' ? formatNumber(value) : value,
                    ]),
                ),
            ),
        })),
);
</script>

<template>
    <ul class="flex flex-col gap-2.5">
        <li v-for="item in items" :key="item.id" class="flex items-start gap-2.5 text-sm leading-relaxed">
            <SeoScoreDot :rating="item.rating" :label="t(`admin.seo.rating.${item.rating}`)" class="mt-1.5" />
            <span>{{ item.message }}</span>
        </li>
    </ul>
</template>
