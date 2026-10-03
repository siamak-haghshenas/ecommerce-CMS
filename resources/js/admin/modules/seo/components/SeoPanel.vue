<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { toApiError } from '@/shared/http/errors';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import TextAreaField from '@/shared/ui/TextAreaField.vue';
import TextField from '@/shared/ui/TextField.vue';
import { seoApi } from '../api';
import type { SeoContentType, SeoSuggestions } from '../types';
import {
    analyzeReadability,
    analyzeSeo,
    DESCRIPTION_MIN_WORDS,
    META_DESCRIPTION_MAX,
    META_DESCRIPTION_MIN,
    ratingOf,
    TITLE_MAX,
    TITLE_MIN,
} from '../analysis';
import LengthMeter from './LengthMeter.vue';
import SeoCheckList from './SeoCheckList.vue';
import SeoScoreDot from './SeoScoreDot.vue';
import SeoSnippetPreview from './SeoSnippetPreview.vue';
import SeoSuggestionsPanel, { type SuggestionField } from './SeoSuggestionsPanel.vue';

/**
 * The SEO section of a content form (category, brand, ...): focus keyphrase, SEO title and meta
 * description with a live analysis (the server stores the same scores on save) and optional AI suggestions.
 */
const props = withDefaults(
    defineProps<{
        type: SeoContentType;
        name: string;
        /** Another way the name is written (e.g. a brand's Persian name), for the AI. */
        alternateName?: string | null;
        slug: string;
        description: string;
        hasImage: boolean;
        /** The saved item, when it exists. */
        subjectId: number | null;
        /** Categories only: the chosen parent, for the AI. */
        parentId?: number | null;
        /** Names shown before the slug in the preview (e.g. parent categories), and given to the AI. */
        path?: string[];
        /** Names of other items of the same type using the same focus keyphrase. */
        keyphraseUsedBy: string[];
        errors: { focus_keyphrase?: string; meta_title?: string; meta_description?: string };
    }>(),
    { alternateName: null, parentId: null, path: () => [] },
);

const keyphrase = defineModel<string>('keyphrase', { required: true });
const metaTitle = defineModel<string>('metaTitle', { required: true });
const metaDescription = defineModel<string>('metaDescription', { required: true });

const { t } = useI18n();
const { formatNumber } = useLocale();

const tab = ref<'seo' | 'readability'>('seo');

const seo = computed(() =>
    analyzeSeo({
        name: props.name,
        slug: props.slug,
        description: props.description,
        metaTitle: metaTitle.value,
        metaDescription: metaDescription.value,
        focusKeyphrase: keyphrase.value,
        hasImage: props.hasImage,
        keyphraseUsedBy: props.keyphraseUsedBy,
    }),
);
const readability = computed(() => analyzeReadability(props.description));

const tabs = computed(() => [
    { key: 'seo' as const, label: t('admin.seo.tabSeo'), score: seo.value.score },
    { key: 'readability' as const, label: t('admin.seo.tabReadability'), score: readability.value.score },
]);

function scoreLabel(score: number | null): string {
    const rating = ratingOf(score);

    return rating === null
        ? t('admin.seo.notAnalysed')
        : t('admin.seo.scoreLabel', { score: formatNumber(score ?? 0), rating: t(`admin.seo.rating.${rating}`) });
}

// ---- AI suggestions ----

const suggestions = ref<SeoSuggestions | null>(null);
const suggesting = ref(false);
const suggestionError = ref<string | null>(null);

// Suggestions belong to the item being edited.
watch(
    () => props.subjectId,
    () => {
        suggestions.value = null;
        suggestionError.value = null;
    },
);

async function suggest(): Promise<void> {
    suggesting.value = true;
    suggestionError.value = null;

    try {
        suggestions.value = await seoApi.suggest({
            type: props.type,
            name: props.name,
            alternate_name: props.alternateName?.trim() || null,
            description: props.description.trim() || null,
            focus_keyphrase: keyphrase.value.trim() || null,
            parent_id: props.type === 'category' ? props.parentId : undefined,
            subject_id: props.subjectId,
        });
    } catch (caught) {
        suggestionError.value = toApiError(caught).message;
    } finally {
        suggesting.value = false;
    }
}

function pick(field: SuggestionField, value: string): void {
    if (field === 'keyphrase') {
        keyphrase.value = value;
    } else if (field === 'title') {
        metaTitle.value = value;
    } else {
        metaDescription.value = value;
    }
}
</script>

<template>
    <section class="flex flex-col gap-4.5 rounded-xl border border-line p-4" :aria-labelledby="'seo-heading'">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-col gap-1">
                <h3 id="seo-heading" class="text-sm font-bold">{{ t('admin.seo.title') }}</h3>
                <p class="text-[13px] leading-relaxed text-fg-muted">{{ t('admin.seo.intro') }}</p>
            </div>
            <AppButton
                variant="secondary"
                size="md"
                :loading="suggesting"
                :disabled="name.trim() === ''"
                :title="name.trim() === '' ? t('admin.seo.aiNeedsName') : undefined"
                @click="suggest"
            >
                <AppIcon name="sparkles" :size="18" />
                {{ suggestions ? t('admin.seo.aiAgain') : t('admin.seo.aiSuggest') }}
            </AppButton>
        </div>

        <FormAlert v-if="suggestionError" :message="suggestionError" />

        <SeoSuggestionsPanel
            v-if="suggestions"
            :suggestions="suggestions"
            :current="{ keyphrase, title: metaTitle, description: metaDescription }"
            @pick="pick"
            @dismiss="suggestions = null"
        />

        <TextField
            v-model="keyphrase"
            :label="t('admin.seo.focusKeyphrase')"
            :hint="t('admin.seo.focusKeyphraseHint')"
            optional
            autocomplete="off"
            :error="errors.focus_keyphrase"
        />

        <TextField
            v-model="metaTitle"
            :label="t('admin.seo.metaTitle')"
            optional
            autocomplete="off"
            :error="errors.meta_title"
        >
            <template #below>
                <LengthMeter :length="[...metaTitle.trim()].length" :min="TITLE_MIN" :max="TITLE_MAX" />
            </template>
        </TextField>

        <div>
            <TextAreaField
                v-model="metaDescription"
                :label="t('admin.seo.metaDescription')"
                optional
                :rows="3"
                :error="errors.meta_description"
            />
            <LengthMeter
                :length="[...metaDescription.trim()].length"
                :min="META_DESCRIPTION_MIN"
                :max="META_DESCRIPTION_MAX"
            />
        </div>

        <SeoSnippetPreview
            :title="metaTitle.trim() || name"
            :description="metaDescription.trim() || description.trim()"
            :path="path"
            :slug="slug"
        />

        <div class="flex flex-col gap-3">
            <div
                role="tablist"
                :aria-label="t('admin.seo.analysis')"
                class="flex gap-1 rounded-xl bg-surface-muted p-1"
            >
                <button
                    v-for="item in tabs"
                    :id="`seo-tab-${item.key}`"
                    :key="item.key"
                    type="button"
                    role="tab"
                    :aria-selected="tab === item.key"
                    :aria-controls="`seo-panel-${item.key}`"
                    :class="[
                        'flex h-10 grow cursor-pointer items-center justify-center gap-2 rounded-[9px] text-sm focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                        tab === item.key ? 'bg-segment-active font-semibold shadow-sm' : 'text-fg-muted hover:text-fg',
                    ]"
                    @click="tab = item.key"
                >
                    <SeoScoreDot :rating="ratingOf(item.score)" :label="scoreLabel(item.score)" />
                    {{ item.label }}
                </button>
            </div>

            <div :id="`seo-panel-${tab}`" role="tabpanel" :aria-labelledby="`seo-tab-${tab}`" class="px-1">
                <SeoCheckList v-if="tab === 'seo'" :checks="seo.checks" />
                <SeoCheckList v-else-if="readability.checks.length > 0" :checks="readability.checks" />
                <p v-else class="text-sm leading-relaxed text-fg-muted">
                    {{ t('admin.seo.readabilityTooShort', { n: formatNumber(DESCRIPTION_MIN_WORDS) }) }}
                </p>
            </div>
        </div>
    </section>
</template>
