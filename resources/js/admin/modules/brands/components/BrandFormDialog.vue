<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApiForm } from '@/shared/composables/useApiForm';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import TextAreaField from '@/shared/ui/TextAreaField.vue';
import TextField from '@/shared/ui/TextField.vue';
import { sameKeyphrase, slugify } from '../../seo/analysis';
import SeoPanel from '../../seo/components/SeoPanel.vue';
import { brandsApi } from '../api';
import type { Brand, SaveBrandPayload } from '../types';
import MediaImageField from '../../media/components/MediaImageField.vue';
import type { MediaPicture } from '../../media/types';

/**
 * Creates (brand null) or edits a brand. The logo is chosen from the media library and saved with
 * the brand.
 */
const props = defineProps<{ open: boolean; brand: Brand | null; brands: Brand[] }>();
const emit = defineEmits<{ close: []; saved: [brand: Brand, created: boolean] }>();

const { t } = useI18n();
const form = useApiForm([
    'name',
    'persian_name',
    'slug',
    'description',
    'website',
    'meta_title',
    'meta_description',
    'focus_keyphrase',
    'is_active',
    'logo_id',
] as const);

const fields = reactive({
    name: '',
    persianName: '',
    slug: '',
    description: '',
    website: '',
    metaTitle: '',
    metaDescription: '',
    focusKeyphrase: '',
    visibility: 'visible' as 'visible' | 'hidden',
});
/** Chosen from the media library; saved with the form. */
const logo = ref<MediaPicture | null>(null);

/** The saved brand: on create, set after the first successful save. */
const saved = ref<Brand | null>(null);
/** Stop suggesting a slug from the name once the admin edits it. */
const slugEdited = ref(false);
let createdInThisDialog = false;

const visibilityOptions = computed(() => [
    { value: 'visible' as const, label: t('admin.brands.visible') },
    { value: 'hidden' as const, label: t('admin.brands.hidden') },
]);

/** Other brands targeting the same keyphrase (the server checks the same on save). */
const keyphraseUsedBy = computed(() =>
    fields.focusKeyphrase.trim() === ''
        ? []
        : props.brands
              .filter(
                  (brand) =>
                      brand.id !== saved.value?.id && sameKeyphrase(brand.focus_keyphrase ?? '', fields.focusKeyphrase),
              )
              .map((brand) => brand.name),
);

const hasLogo = computed(() => logo.value !== null);

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        const brand = props.brand;
        form.clearErrors();
        saved.value = brand;
        createdInThisDialog = false;
        fields.name = brand?.name ?? '';
        fields.persianName = brand?.persian_name ?? '';
        fields.slug = brand?.slug ?? '';
        fields.description = brand?.description ?? '';
        fields.website = brand?.website ?? '';
        fields.metaTitle = brand?.meta_title ?? '';
        fields.metaDescription = brand?.meta_description ?? '';
        fields.focusKeyphrase = brand?.focus_keyphrase ?? '';
        fields.visibility = brand && !brand.is_active ? 'hidden' : 'visible';
        logo.value = brand?.logo ?? null;
        slugEdited.value = brand !== null;
    },
);

watch(
    () => fields.name,
    (name) => {
        if (!slugEdited.value) {
            fields.slug = slugify(name);
        }
    },
);

function onSlugInput(value: string): void {
    fields.slug = value;
    slugEdited.value = true;
}

function optional(value: string): string | null {
    return value.trim() === '' ? null : value.trim();
}

async function save(): Promise<void> {
    const payload: SaveBrandPayload = {
        name: fields.name,
        persian_name: optional(fields.persianName),
        slug: fields.slug,
        description: optional(fields.description),
        website: optional(fields.website),
        meta_title: optional(fields.metaTitle),
        meta_description: optional(fields.metaDescription),
        focus_keyphrase: optional(fields.focusKeyphrase),
        is_active: fields.visibility === 'visible',
        logo_id: logo.value?.id ?? null,
    };

    const result = await form.submit(async () => {
        const existing = saved.value;
        const brand =
            existing === null ? await brandsApi.create(payload) : await brandsApi.update(existing.id, payload);

        if (existing === null) {
            createdInThisDialog = true;
        }
        saved.value = brand;

        return brand;
    });

    if (result) {
        emit('saved', result, createdInThisDialog);
    }
}
</script>

<template>
    <AppDialog
        :open="open"
        :title="saved ? t('admin.brands.editTitle') : t('admin.brands.createTitle')"
        size="lg"
        :busy="form.processing.value"
        @close="emit('close')"
    >
        <form id="brand-form" class="flex flex-col gap-4.5" novalidate @submit.prevent="save">
            <FormAlert v-if="form.generalError.value" :message="form.generalError.value" />

            <TextField
                v-model="fields.name"
                :label="t('admin.brands.name')"
                :hint="t('admin.brands.nameHint')"
                autocomplete="off"
                :error="form.errors.name"
            />

            <TextField
                v-model="fields.persianName"
                :label="t('admin.brands.persianName')"
                :hint="t('admin.brands.persianNameHint')"
                dir="rtl"
                optional
                autocomplete="off"
                :error="form.errors.persian_name"
            />

            <TextField
                :model-value="fields.slug"
                :label="t('admin.brands.slug')"
                dir="ltr"
                autocomplete="off"
                :hint="t('admin.brands.slugHint')"
                :error="form.errors.slug"
                @update:model-value="onSlugInput"
            />

            <MediaImageField
                v-model="logo"
                :label="t('admin.brands.logo')"
                :hint="t('admin.brands.logoHint')"
                :error="form.errors.logo_id"
            />

            <TextAreaField
                v-model="fields.description"
                :label="t('admin.brands.description')"
                optional
                :rows="3"
                :maxlength="5000"
                :error="form.errors.description"
            />

            <TextField
                v-model="fields.website"
                :label="t('admin.brands.website')"
                dir="ltr"
                inputmode="text"
                placeholder="https://"
                optional
                autocomplete="off"
                :hint="t('admin.brands.websiteHint')"
                :error="form.errors.website"
            />

            <div class="flex flex-col gap-2">
                <span class="text-sm font-semibold">{{ t('admin.brands.visibility') }}</span>
                <SegmentedControl
                    v-model="fields.visibility"
                    :label="t('admin.brands.visibility')"
                    :options="visibilityOptions"
                />
                <p class="text-[13px] leading-relaxed text-fg-muted">{{ t('admin.brands.visibilityHint') }}</p>
            </div>

            <SeoPanel
                v-model:keyphrase="fields.focusKeyphrase"
                v-model:meta-title="fields.metaTitle"
                v-model:meta-description="fields.metaDescription"
                type="brand"
                :name="fields.name"
                :alternate-name="fields.persianName"
                :slug="fields.slug"
                :description="fields.description"
                :has-image="hasLogo"
                :subject-id="saved?.id ?? null"
                :keyphrase-used-by="keyphraseUsedBy"
                :errors="{
                    focus_keyphrase: form.errors.focus_keyphrase,
                    meta_title: form.errors.meta_title,
                    meta_description: form.errors.meta_description,
                }"
            />
        </form>

        <template #footer>
            <AppButton type="submit" form="brand-form" size="md" :loading="form.processing.value">
                {{ saved ? t('admin.brands.save') : t('admin.brands.create') }}
            </AppButton>
            <AppButton variant="secondary" size="md" :disabled="form.processing.value" @click="emit('close')">
                {{ t('common.cancel') }}
            </AppButton>
        </template>
    </AppDialog>
</template>
