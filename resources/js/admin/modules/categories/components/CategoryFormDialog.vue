<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApiForm } from '@/shared/composables/useApiForm';
import { toApiError } from '@/shared/http/errors';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import TextAreaField from '@/shared/ui/TextAreaField.vue';
import TextField from '@/shared/ui/TextField.vue';
import { attributesApi } from '../../attributes/api';
import { categoriesApi } from '../api';
import { sameKeyphrase } from '../../seo/analysis';
import SeoPanel from '../../seo/components/SeoPanel.vue';
import { branchIds, findNode, flatten, suggestSlug } from '../tree';
import type { Category, CategoryNode, SaveCategoryPayload, CategoryType } from '../types';
import CategoryAttributesField from './CategoryAttributesField.vue';
import CategoryParentSelect from './CategoryParentSelect.vue';
import MediaImageField from '../../media/components/MediaImageField.vue';
import type { MediaPicture } from '../../media/types';

/**
 * Creates (categoryId null) or edits a category. The image is chosen from the media library and
 * saved with the category; its own attributes are saved right after it.
 */
const props = defineProps<{
    open: boolean;
    categoryId: number | null;
    /** Preselected parent when creating ("add sub-category"). */
    parentId?: number | null;
    tree: CategoryNode[];
    /** Product categories have specifications; blog categories don't. */
    type?: CategoryType;
}>();
const categoryType = computed<CategoryType>(() => props.type ?? 'product');
const emit = defineEmits<{ close: []; saved: [category: Category, created: boolean] }>();

const { t } = useI18n();
const form = useApiForm([
    'name',
    'slug',
    'parent_id',
    'description',
    'meta_title',
    'meta_description',
    'focus_keyphrase',
    'is_active',
    'image_id',
] as const);

const fields = reactive({
    name: '',
    slug: '',
    parentId: null as number | null,
    description: '',
    metaTitle: '',
    metaDescription: '',
    focusKeyphrase: '',
    visibility: 'visible' as 'visible' | 'hidden',
});
/** Chosen from the media library; saved with the form. */
const image = ref<MediaPicture | null>(null);
/** The category's own attributes (inherited ones come from the parent); saved after the category. */
const attributeIds = ref<number[]>([]);
let savedAttributeIds: number[] = [];

/** Set once the category exists: on create, after the first successful save. */
const savedId = ref<number | null>(null);
const loaded = ref<Category | null>(null);
const loading = ref(false);
const loadError = ref<string | null>(null);
/** Stop suggesting a slug from the name once the admin edits it. */
const slugEdited = ref(false);
let createdInThisDialog = false;

const isEditing = computed(() => savedId.value !== null);
const visibilityOptions = computed(() => [
    { value: 'visible' as const, label: t('admin.categories.visible') },
    { value: 'hidden' as const, label: t('admin.categories.hidden') },
]);
/** The category and its descendants can't become its parent. */
const excludedParents = computed(() => {
    const node = savedId.value === null ? null : findNode(props.tree, savedId.value);

    return node ? branchIds(node) : new Set<number>();
});

/** Ancestors and parent of the chosen parent, top first: for the search preview and the AI. */
const parentPath = computed(() => {
    const parent = flatten(props.tree).find((item) => item.node.id === fields.parentId);

    return parent ? [...parent.path, parent.node.name] : [];
});

/** Other categories targeting the same keyphrase (the server checks the same on save). */
const keyphraseUsedBy = computed(() =>
    fields.focusKeyphrase.trim() === ''
        ? []
        : flatten(props.tree)
              .filter(
                  ({ node }) =>
                      node.id !== savedId.value && sameKeyphrase(node.focus_keyphrase ?? '', fields.focusKeyphrase),
              )
              .map(({ node }) => node.name),
);

const parentName = computed(
    () => flatten(props.tree).find((item) => item.node.id === fields.parentId)?.node.name ?? null,
);

const hasImage = computed(() => image.value !== null);

function fill(category: Category | null): void {
    fields.name = category?.name ?? '';
    fields.slug = category?.slug ?? '';
    fields.parentId = category ? category.parent_id : (props.parentId ?? null);
    fields.description = category?.description ?? '';
    fields.metaTitle = category?.meta_title ?? '';
    fields.metaDescription = category?.meta_description ?? '';
    fields.focusKeyphrase = category?.focus_keyphrase ?? '';
    fields.visibility = category && !category.is_active ? 'hidden' : 'visible';
    image.value = category?.image ?? null;
    slugEdited.value = category !== null;
    attributeIds.value = [];
    savedAttributeIds = [];
}

async function load(id: number): Promise<void> {
    loading.value = true;
    loadError.value = null;

    try {
        const [category, attributes] = await Promise.all([
            categoriesApi.show(id),
            categoryType.value === 'product' ? attributesApi.forCategory(id) : Promise.resolve({ own: [] }),
        ]);
        loaded.value = category;
        fill(category);
        savedAttributeIds = attributes.own.map((attribute) => attribute.id);
        attributeIds.value = [...savedAttributeIds];
    } catch (caught) {
        loadError.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        form.clearErrors();
        savedId.value = props.categoryId;
        loaded.value = null;
        createdInThisDialog = false;
        fill(null);

        if (props.categoryId !== null) {
            void load(props.categoryId);
        }
    },
);

watch(
    () => fields.name,
    (name) => {
        if (!slugEdited.value) {
            fields.slug = suggestSlug(name);
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
    const payload: SaveCategoryPayload = {
        type: categoryType.value,
        name: fields.name,
        slug: fields.slug,
        parent_id: fields.parentId,
        description: optional(fields.description),
        meta_title: optional(fields.metaTitle),
        meta_description: optional(fields.metaDescription),
        focus_keyphrase: optional(fields.focusKeyphrase),
        is_active: fields.visibility === 'visible',
        image_id: image.value?.id ?? null,
    };

    const saved = await form.submit(async () => {
        const category =
            savedId.value === null
                ? await categoriesApi.create(payload)
                : await categoriesApi.update(savedId.value, payload);

        if (savedId.value === null) {
            createdInThisDialog = true;
        }
        savedId.value = category.id;
        loaded.value = category;

        if (categoryType.value === 'product' && attributeIds.value.join() !== savedAttributeIds.join()) {
            await attributesApi.setForCategory(category.id, attributeIds.value);
            savedAttributeIds = [...attributeIds.value];
        }

        return category;
    });

    if (saved) {
        emit('saved', saved, createdInThisDialog);
    }
}
</script>

<template>
    <AppDialog
        :open="open"
        :title="isEditing ? t('admin.categories.editTitle') : t('admin.categories.createTitle')"
        size="lg"
        :busy="form.processing.value"
        @close="emit('close')"
    >
        <div v-if="loading" class="flex justify-center py-10" role="status" :aria-label="t('admin.categories.loading')">
            <AppSpinner />
        </div>

        <div v-else-if="loadError" class="flex flex-col gap-3">
            <FormAlert :message="loadError" />
            <AppButton variant="secondary" size="md" @click="categoryId !== null && load(categoryId)">
                {{ t('common.retry') }}
            </AppButton>
        </div>

        <form v-else id="category-form" class="flex flex-col gap-4.5" novalidate @submit.prevent="save">
            <FormAlert v-if="form.generalError.value" :message="form.generalError.value" />

            <TextField
                v-model="fields.name"
                :label="t('admin.categories.name')"
                autocomplete="off"
                :error="form.errors.name"
            />

            <TextField
                :model-value="fields.slug"
                :label="t('admin.categories.slug')"
                dir="ltr"
                autocomplete="off"
                :hint="t('admin.categories.slugHint')"
                :error="form.errors.slug"
                @update:model-value="onSlugInput"
            />

            <CategoryParentSelect
                v-model="fields.parentId"
                :label="t('admin.categories.parent')"
                :tree="tree"
                :exclude="excludedParents"
                :error="form.errors.parent_id"
            />

            <TextAreaField
                v-model="fields.description"
                :label="t('admin.categories.description')"
                optional
                :rows="3"
                :maxlength="5000"
                :error="form.errors.description"
            />

            <MediaImageField v-model="image" :label="t('admin.categories.image')" :error="form.errors.image_id" />

            <div class="flex flex-col gap-2">
                <span class="text-sm font-semibold">{{ t('admin.categories.visibility') }}</span>
                <SegmentedControl
                    v-model="fields.visibility"
                    :label="t('admin.categories.visibility')"
                    :options="visibilityOptions"
                />
                <p class="text-[13px] leading-relaxed text-fg-muted">{{ t('admin.categories.visibilityHint') }}</p>
            </div>

            <CategoryAttributesField
                v-if="categoryType === 'product'"
                v-model="attributeIds"
                :parent-id="fields.parentId"
                :parent-name="parentName"
            />

            <SeoPanel
                v-model:keyphrase="fields.focusKeyphrase"
                v-model:meta-title="fields.metaTitle"
                v-model:meta-description="fields.metaDescription"
                :type="categoryType === 'blog' ? 'blog_category' : 'category'"
                :name="fields.name"
                :slug="fields.slug"
                :description="fields.description"
                :has-image="hasImage"
                :parent-id="fields.parentId"
                :subject-id="savedId"
                :path="parentPath"
                :keyphrase-used-by="keyphraseUsedBy"
                :errors="{
                    focus_keyphrase: form.errors.focus_keyphrase,
                    meta_title: form.errors.meta_title,
                    meta_description: form.errors.meta_description,
                }"
            />
        </form>

        <template #footer>
            <AppButton
                type="submit"
                form="category-form"
                size="md"
                :loading="form.processing.value"
                :disabled="loading || loadError !== null"
            >
                {{ isEditing ? t('admin.categories.save') : t('admin.categories.create') }}
            </AppButton>
            <AppButton variant="secondary" size="md" :disabled="form.processing.value" @click="emit('close')">
                {{ t('common.cancel') }}
            </AppButton>
        </template>
    </AppDialog>
</template>
