<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import AppIcon from '@/shared/ui/AppIcon.vue';
import type { SeoSuggestions } from '../types';
import { META_DESCRIPTION_MAX, META_DESCRIPTION_MIN, TITLE_MAX, TITLE_MIN, type SeoRating } from '../analysis';
import SeoScoreDot from './SeoScoreDot.vue';

export type SuggestionField = 'keyphrase' | 'title' | 'description';

/** AI options grouped by field. Picking one only fills the form field; saving is still up to the admin. */
const props = defineProps<{
    suggestions: SeoSuggestions;
    current: Record<SuggestionField, string>;
}>();
const emit = defineEmits<{ pick: [field: SuggestionField, value: string]; dismiss: [] }>();

const { t } = useI18n();
const { formatNumber } = useLocale();

function lengthRating(text: string, min: number, max: number): SeoRating {
    const length = [...text].length;

    return length > max ? 'bad' : length < min ? 'ok' : 'good';
}

const groups = computed(() => [
    {
        field: 'keyphrase' as const,
        label: t('admin.seo.focusKeyphrase'),
        options: props.suggestions.keyphrases.map((text) => ({ text, rating: null })),
    },
    {
        field: 'title' as const,
        label: t('admin.seo.metaTitle'),
        options: props.suggestions.titles.map((text) => ({ text, rating: lengthRating(text, TITLE_MIN, TITLE_MAX) })),
    },
    {
        field: 'description' as const,
        label: t('admin.seo.metaDescription'),
        options: props.suggestions.meta_descriptions.map((text) => ({
            text,
            rating: lengthRating(text, META_DESCRIPTION_MIN, META_DESCRIPTION_MAX),
        })),
    },
]);
</script>

<template>
    <section
        class="flex flex-col gap-4 rounded-xl border border-primary/30 bg-primary-soft/40 p-4"
        :aria-label="t('admin.seo.aiTitle')"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="flex flex-col gap-1">
                <h3 class="flex items-center gap-2 text-sm font-bold">
                    <AppIcon name="sparkles" :size="18" class="text-primary" />
                    {{ t('admin.seo.aiTitle') }}
                </h3>
                <p class="text-[13px] leading-relaxed text-fg-muted">{{ t('admin.seo.aiPickHint') }}</p>
            </div>
            <button
                type="button"
                :aria-label="t('admin.seo.aiDismiss')"
                class="flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-fg-muted hover:bg-surface hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                @click="emit('dismiss')"
            >
                <AppIcon name="close" :size="18" />
            </button>
        </div>

        <div v-for="group in groups" :key="group.field" class="flex flex-col gap-2">
            <span class="text-xs font-semibold text-fg-muted">{{ group.label }}</span>
            <ul class="flex flex-col gap-1.5">
                <li v-for="option in group.options" :key="option.text">
                    <button
                        type="button"
                        dir="auto"
                        :aria-pressed="current[group.field] === option.text"
                        :class="[
                            'flex w-full cursor-pointer items-start gap-2.5 rounded-lg border px-3 py-2.5 text-start text-sm leading-relaxed transition-colors focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                            current[group.field] === option.text
                                ? 'border-primary bg-surface font-semibold'
                                : 'border-line bg-surface hover:border-primary/60',
                        ]"
                        @click="emit('pick', group.field, option.text)"
                    >
                        <AppIcon
                            v-if="current[group.field] === option.text"
                            name="check"
                            :size="16"
                            class="mt-1 text-primary"
                        />
                        <span class="grow">{{ option.text }}</span>
                        <span v-if="option.rating" class="flex shrink-0 items-center gap-1.5 text-xs text-fg-muted">
                            <SeoScoreDot
                                :rating="option.rating"
                                :label="t(`admin.seo.rating.${option.rating}`)"
                                size="sm"
                            />
                            {{ formatNumber([...option.text].length) }}
                        </span>
                    </button>
                </li>
            </ul>
        </div>
    </section>
</template>
