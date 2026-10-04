<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { onBeforeRouteLeave, useRouter } from 'vue-router';
import { useApiForm } from '@/shared/composables/useApiForm';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import AppBadge from '@/shared/ui/AppBadge.vue';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import NumberField from '@/shared/ui/NumberField.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import TextField from '@/shared/ui/TextField.vue';
import { Permission } from '../../../permissions';
import { optionsApi } from '../../attributes/api';
import type { Attribute, Option } from '../../attributes/types';
import { useAdminAuthStore } from '../../auth/store';
import { brandsApi } from '../../brands/api';
import type { Brand } from '../../brands/types';
import { categoriesApi } from '../../categories/api';
import { flatten } from '../../categories/tree';
import type { CategoryNode } from '../../categories/types';
import { slugify } from '../../seo/analysis';
import SeoPanel from '../../seo/components/SeoPanel.vue';
import { productsApi } from '../api';
import CategoriesField from '../components/CategoriesField.vue';
import ImagesManager from '../components/ImagesManager.vue';
import RichTextEditor from '../../editor/components/RichTextEditor.vue';
import SpecificationsEditor, { type SpecificationInput } from '../components/SpecificationsEditor.vue';
import StockHistoryDialog from '../components/StockHistoryDialog.vue';
import TagsInput from '../components/TagsInput.vue';
import VariantsEditor, { type ChosenOption } from '../components/VariantsEditor.vue';
import { htmlToText } from '../../editor/richText';
import type { Product, ProductImage, ProductStatus, ProductType, SaveProductPayload } from '../types';
import { fromLocalInput, newRow, rowFromVariant, toLocalInput, toPayload, type VariantRow } from '../variants';

/** Create (`id` null) or edit a product. Everything, the gallery included, is saved with one Save. */
const props = defineProps<{ id: number | null }>();

const { t } = useI18n();
const { formatDateTime } = useLocale();
const toast = useToast();
const router = useRouter();
const auth = useAdminAuthStore();
const canManage = computed(() => auth.can(Permission.ManageProducts));

const FIELDS = [
    'name',
    'slug',
    'short_description',
    'description',
    'status',
    'published_at',
    'brand_id',
    'category_ids',
    'primary_category_id',
    'tags',
    'track_stock',
    'allow_backorders',
    'low_stock_threshold',
    'weight_grams',
    'length_mm',
    'width_mm',
    'height_mm',
    'meta_title',
    'meta_description',
    'focus_keyphrase',
    'option_ids',
] as const;
const form = useApiForm(FIELDS);
/** Fields whose error is shown next to them; any other error goes in the alert at the top. */
const SHOWN_ON_FIELD: string[] = [
    'name',
    'slug',
    'short_description',
    'description',
    'category_ids',
    'primary_category_id',
    'low_stock_threshold',
    'weight_grams',
    'length_mm',
    'width_mm',
    'height_mm',
    'meta_title',
    'meta_description',
    'focus_keyphrase',
    'option_ids',
];

// ---- reference data ----

const tree = ref<CategoryNode[]>([]);
const brands = ref<Brand[]>([]);
const options = ref<Option[]>([]);

// ---- the product being edited ----

const product = ref<Product | null>(null);
const loading = ref(true);
const loadError = ref<string | null>(null);

const fields = reactive({
    type: 'simple' as ProductType,
    name: '',
    slug: '',
    shortDescription: '',
    description: '',
    status: 'draft' as ProductStatus,
    publishedAt: '',
    brandId: null as number | null,
    categoryIds: [] as number[],
    primaryCategoryId: null as number | null,
    tags: [] as string[],
    trackStock: true,
    allowBackorders: false,
    lowStockThreshold: null as number | null,
    weightGrams: null as number | null,
    lengthMm: null as number | null,
    widthMm: null as number | null,
    heightMm: null as number | null,
    metaTitle: '',
    metaDescription: '',
    focusKeyphrase: '',
});
const chosen = ref<ChosenOption[]>([]);
const rows = ref<VariantRow[]>([newRow()]);
const images = ref<ProductImage[]>([]);

// A variant's picture must stay in the gallery: removing it from the gallery clears it.
watch(images, (list) => {
    const ids = new Set(list.map((image) => image.id));

    for (const row of rows.value) {
        if (row.imageId !== null && !ids.has(row.imageId)) {
            row.imageId = null;
        }
    }
});
const specValues = ref<Record<number, SpecificationInput>>({});
const slugEdited = ref(false);

function fill(loaded: Product | null): void {
    product.value = loaded;
    fields.type = loaded?.type ?? 'simple';
    fields.name = loaded?.name ?? '';
    fields.slug = loaded?.slug ?? '';
    fields.shortDescription = loaded?.short_description ?? '';
    fields.description = loaded?.description ?? '';
    fields.status = loaded?.status ?? 'draft';
    fields.publishedAt = toLocalInput(loaded?.published_at ?? null);
    fields.brandId = loaded?.brand?.id ?? null;
    fields.categoryIds = loaded?.categories.map((category) => category.id) ?? [];
    fields.primaryCategoryId = loaded?.categories.find((category) => category.is_primary)?.id ?? null;
    fields.tags = loaded?.tags.map((tag) => tag.name) ?? [];
    fields.trackStock = loaded?.track_stock ?? true;
    fields.allowBackorders = loaded?.allow_backorders ?? false;
    fields.lowStockThreshold = loaded?.low_stock_threshold ?? null;
    fields.weightGrams = loaded?.weight_grams ?? null;
    fields.lengthMm = loaded?.length_mm ?? null;
    fields.widthMm = loaded?.width_mm ?? null;
    fields.heightMm = loaded?.height_mm ?? null;
    fields.metaTitle = loaded?.meta_title ?? '';
    fields.metaDescription = loaded?.meta_description ?? '';
    fields.focusKeyphrase = loaded?.focus_keyphrase ?? '';
    chosen.value =
        loaded?.options.map((option) => ({
            optionId: option.id,
            // The values its variants use.
            valueIds: option.values
                .map((value) => value.id)
                .filter((valueId) => loaded.variants.some((variant) => variant.option_value_ids.includes(valueId))),
        })) ?? [];
    rows.value = loaded ? loaded.variants.map(rowFromVariant) : [newRow()];
    images.value = loaded?.images ?? [];
    specValues.value = Object.fromEntries(
        (loaded?.specifications ?? []).map((spec) => [
            spec.attribute_id,
            {
                valueIds: spec.value_ids,
                text: spec.text ?? '',
                number: spec.number === null ? '' : String(Number(spec.number)),
                boolean: spec.boolean,
            },
        ]),
    );
    slugEdited.value = loaded !== null;
}

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;

    try {
        const [loadedTree, loadedBrands, loadedOptions, loaded] = await Promise.all([
            categoriesApi.tree(),
            brandsApi.list(),
            optionsApi.list(),
            props.id === null ? Promise.resolve(null) : productsApi.show(props.id),
        ]);
        tree.value = loadedTree;
        brands.value = loadedBrands;
        options.value = loadedOptions;
        fill(loaded);
        await loadSpecifications();
        snapshot.value = JSON.stringify(payload());
    } catch (caught) {
        loadError.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

onMounted(load);

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

// ---- specifications follow the categories ----

const attributes = ref<Attribute[]>([]);
const attributesLoading = ref(false);

async function loadSpecifications(): Promise<void> {
    attributesLoading.value = true;

    try {
        attributes.value =
            fields.categoryIds.length === 0 ? [] : await productsApi.applicableAttributes(fields.categoryIds);

        // An entry for every attribute, so the editor never creates them while rendering.
        for (const attribute of attributes.value) {
            specValues.value[attribute.id] ??= { valueIds: [], text: '', number: '', boolean: null };
        }
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    } finally {
        attributesLoading.value = false;
    }
}

watch(
    () => [...fields.categoryIds],
    (next, previous) => {
        if (!loading.value && next.join() !== previous.join()) {
            void loadSpecifications();
        }
    },
);

/** Only filled-in specifications of the current categories are sent. */
function specificationsPayload(): SaveProductPayload['specifications'] {
    return attributes.value.flatMap((attribute): SaveProductPayload['specifications'] => {
        const value = specValues.value[attribute.id];

        if (!value) {
            return [];
        }

        switch (attribute.type) {
            case 'select':
                return value.valueIds.length > 0 ? [{ attribute_id: attribute.id, value_ids: value.valueIds }] : [];
            case 'text':
                return value.text.trim() !== '' ? [{ attribute_id: attribute.id, text: value.text.trim() }] : [];
            case 'number': {
                const number = Number(value.number.replace(/[٫,]/g, '.'));

                return value.number.trim() !== '' && !Number.isNaN(number)
                    ? [{ attribute_id: attribute.id, number }]
                    : [];
            }
            default:
                return value.boolean !== null ? [{ attribute_id: attribute.id, boolean: value.boolean }] : [];
        }
    });
}

// ---- payload, unsaved changes ----

const optional = (value: string): string | null => (value.trim() === '' ? null : value.trim());

function payload(): SaveProductPayload {
    return {
        type: fields.type,
        name: fields.name.trim(),
        slug: fields.slug,
        short_description: optional(fields.shortDescription),
        description: optional(fields.description),
        status: fields.status,
        published_at: fields.status === 'published' ? fromLocalInput(fields.publishedAt) : null,
        brand_id: fields.brandId,
        category_ids: fields.categoryIds,
        primary_category_id: fields.primaryCategoryId,
        tags: fields.tags,
        track_stock: fields.trackStock,
        allow_backorders: fields.allowBackorders,
        low_stock_threshold: fields.lowStockThreshold,
        weight_grams: fields.weightGrams,
        length_mm: fields.lengthMm,
        width_mm: fields.widthMm,
        height_mm: fields.heightMm,
        meta_title: optional(fields.metaTitle),
        meta_description: optional(fields.metaDescription),
        focus_keyphrase: optional(fields.focusKeyphrase),
        option_ids: fields.type === 'variable' ? chosen.value.map((item) => item.optionId) : [],
        variants: rows.value.map((row) => toPayload(row, fields.trackStock)),
        specifications: specificationsPayload(),
        image_ids: images.value.map((image) => image.id),
    };
}

const snapshot = ref('');
/**
 * The editor tidies the HTML it loads (e.g. its own markup): that is not a change by the admin, so
 * the saved baseline takes the tidied text of that field.
 */
function acceptNormalized(field: 'short_description' | 'description'): void {
    if (snapshot.value === '') {
        return;
    }

    const baseline = JSON.parse(snapshot.value) as SaveProductPayload;
    baseline[field] = payload()[field];
    snapshot.value = JSON.stringify(baseline);
}

const dirty = computed(() => !loading.value && snapshot.value !== '' && JSON.stringify(payload()) !== snapshot.value);

// ---- errors ----

const fieldErrors = computed(() => form.lastError.value?.fieldErrors ?? {});
/** The specification ids in the order they were last sent, to place `specifications.{i}` errors. */
const sentSpecIds = ref<number[]>([]);
const specErrors = computed(() => {
    const errors: Record<number, string> = {};

    for (const [key, messages] of Object.entries(fieldErrors.value)) {
        const match = /^specifications\.(\d+)/.exec(key);
        const attributeId = match ? sentSpecIds.value[Number(match[1])] : undefined;

        if (attributeId !== undefined && messages[0]) {
            errors[attributeId] ??= messages[0];
        }
    }

    return errors;
});
/** Errors without a field on the page go in the alert at the top. */
const alertMessage = computed(() => {
    const keys = Object.keys(fieldErrors.value);
    const placed = (key: string): boolean =>
        SHOWN_ON_FIELD.includes(key) || key.startsWith('variants') || key.startsWith('specifications');

    return keys.length > 0 && keys.every(placed) && form.lastError.value?.code === 'validation_failed'
        ? null
        : form.generalError.value;
});

// ---- saving ----

async function save(): Promise<void> {
    const body = payload();
    sentSpecIds.value = body.specifications.map((spec) => spec.attribute_id);
    const existing = product.value;

    const saved = await form.submit(() =>
        existing ? productsApi.update(existing.id, body) : productsApi.create(body),
    );

    if (!saved) {
        toast.show(t('admin.products.saveFailed'), 'danger');

        return;
    }

    fill(saved);
    await loadSpecifications();
    snapshot.value = JSON.stringify(payload());
    toast.show(t(existing ? 'admin.products.saved' : 'admin.products.created', { name: saved.name }));

    if (!existing) {
        await router.replace({ name: 'product', params: { id: saved.id } });
    }
}

// ---- delete / restore ----

const confirmDelete = ref(false);

async function remove(): Promise<void> {
    if (!product.value) {
        return;
    }

    await productsApi.remove(product.value.id);
    snapshot.value = JSON.stringify(payload());
    toast.show(t('admin.products.deleted', { name: product.value.name }));
    await router.push({ name: 'products' });
}

async function restore(): Promise<void> {
    if (!product.value) {
        return;
    }

    fill(await productsApi.restore(product.value.id));
    snapshot.value = JSON.stringify(payload());
    toast.show(t('admin.products.restored', { name: fields.name }));
}

// ---- leaving with unsaved changes ----

const leaveTarget = ref<null | (() => void)>(null);
let leaveCancel: (() => void) | null = null;

onBeforeRouteLeave(
    (to) =>
        new Promise<boolean>((resolve) => {
            if (!dirty.value || to.name === 'product') {
                resolve(true);

                return;
            }

            leaveTarget.value = () => resolve(true);
            leaveCancel = () => resolve(false);
        }),
);

function stay(): void {
    leaveCancel?.();
    leaveTarget.value = null;
}

function leave(): void {
    leaveTarget.value?.();
    leaveTarget.value = null;
}

const beforeUnload = (event: BeforeUnloadEvent): void => {
    if (dirty.value) {
        event.preventDefault();
    }
};
window.addEventListener('beforeunload', beforeUnload);
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));

// ---- page ----

const statusOptions = computed(() => [
    { value: 'draft' as const, label: t('admin.products.status.draft') },
    { value: 'published' as const, label: t('admin.products.status.published') },
    { value: 'archived' as const, label: t('admin.products.status.archived') },
]);
const stockModes = computed(() => [
    { value: 'track' as const, label: t('admin.products.stock.track') },
    { value: 'untracked' as const, label: t('admin.products.stock.untracked') },
]);
const stockMode = computed({
    get: () => (fields.trackStock ? 'track' : 'untracked'),
    set: (value: 'track' | 'untracked') => (fields.trackStock = value === 'track'),
});

const sections = computed(() => [
    { id: 'general', label: t('admin.products.sections.general') },
    { id: 'organization', label: t('admin.products.sections.organization') },
    { id: 'pricing', label: t('admin.products.sections.pricing') },
    { id: 'images', label: t('admin.products.sections.images') },
    { id: 'specifications', label: t('admin.products.sections.specifications') },
    { id: 'shipping', label: t('admin.products.sections.shipping') },
    { id: 'seo', label: t('admin.products.sections.seo') },
]);

const primaryPath = computed(() => {
    const primary = flatten(tree.value).find((item) => item.node.id === fields.primaryCategoryId);

    return primary ? [...primary.path, primary.node.name] : [];
});
const isScheduled = computed(
    () => fields.status === 'published' && fields.publishedAt !== '' && new Date(fields.publishedAt) > new Date(),
);
const stockHistoryOpen = ref(false);

const cardClass = 'flex scroll-mt-24 flex-col gap-4.5 rounded-2xl border border-line bg-surface p-5 sm:p-6';
const selectClass =
    'h-13 w-full cursor-pointer rounded-xl border border-line-strong bg-surface px-3.5 text-[15px] text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft';
</script>

<template>
    <div class="mx-auto flex max-w-6xl flex-col gap-5">
        <div class="flex flex-wrap items-center gap-3">
            <RouterLink
                :to="{ name: 'products' }"
                class="flex items-center gap-1.5 rounded-lg text-sm font-medium text-fg-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            >
                <AppIcon name="arrowForward" :size="16" flip-in-rtl class="rotate-180" />
                {{ t('admin.products.backToList') }}
            </RouterLink>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 flex-wrap items-center gap-2.5">
                <h1 class="truncate text-2xl font-bold">{{ product ? product.name : t('admin.products.newTitle') }}</h1>
                <AppBadge v-if="product?.deleted_at" tone="danger">{{ t('admin.products.deletedBadge') }}</AppBadge>
                <AppBadge v-else-if="product" :tone="product.status === 'published' ? 'success' : 'neutral'">
                    {{ t(`admin.products.status.${product.status}`) }}
                </AppBadge>
            </div>
            <div v-if="product" class="flex flex-wrap gap-2">
                <AppButton variant="secondary" size="md" @click="stockHistoryOpen = true">
                    <AppIcon name="history" :size="18" />
                    {{ t('admin.products.stockHistory.open') }}
                </AppButton>
                <AppButton v-if="canManage && product.deleted_at" variant="secondary" size="md" @click="restore">
                    <AppIcon name="refresh" :size="18" />
                    {{ t('admin.products.restore') }}
                </AppButton>
                <AppButton v-else-if="canManage" variant="danger-outline" size="md" @click="confirmDelete = true">
                    <AppIcon name="trash" :size="18" />
                    {{ t('admin.products.delete') }}
                </AppButton>
            </div>
        </div>

        <div v-if="loading" class="flex justify-center py-16"><AppSpinner /></div>
        <div v-else-if="loadError" class="flex flex-col gap-3">
            <FormAlert :message="loadError" />
            <div>
                <AppButton variant="secondary" size="md" @click="load">{{ t('common.retry') }}</AppButton>
            </div>
        </div>

        <div v-else class="grid gap-5 lg:grid-cols-[200px_1fr]">
            <nav :aria-label="t('admin.products.sectionsLabel')" class="hidden lg:block">
                <ul class="sticky top-24 flex flex-col gap-1">
                    <li v-for="section in sections" :key="section.id">
                        <a
                            :href="`#${section.id}`"
                            class="block rounded-lg px-3 py-2 text-sm text-fg-muted hover:bg-surface-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                        >
                            {{ section.label }}
                        </a>
                    </li>
                </ul>
            </nav>

            <form id="product-form" class="flex min-w-0 flex-col gap-5" novalidate @submit.prevent="save">
                <FormAlert v-if="alertMessage" :message="alertMessage" />

                <section id="general" :class="cardClass" :aria-labelledby="'general-title'">
                    <h2 id="general-title" class="text-lg font-bold">{{ t('admin.products.sections.general') }}</h2>
                    <TextField
                        v-model="fields.name"
                        :label="t('admin.products.name')"
                        autocomplete="off"
                        :error="form.errors.name"
                    />
                    <TextField
                        :model-value="fields.slug"
                        :label="t('admin.products.slug')"
                        :hint="t('admin.products.slugHint')"
                        dir="ltr"
                        autocomplete="off"
                        :error="form.errors.slug"
                        @update:model-value="onSlugInput"
                    />
                    <RichTextEditor
                        v-model="fields.shortDescription"
                        variant="basic"
                        :label="t('admin.products.shortDescription')"
                        :hint="t('admin.products.shortDescriptionHint')"
                        optional
                        :max-chars="1000"
                        :error="form.errors.short_description"
                        @normalized="acceptNormalized('short_description')"
                    />
                    <RichTextEditor
                        v-model="fields.description"
                        :label="t('admin.products.description')"
                        optional
                        :error="form.errors.description"
                        @normalized="acceptNormalized('description')"
                    />
                </section>

                <section id="organization" :class="cardClass" aria-labelledby="organization-title">
                    <h2 id="organization-title" class="text-lg font-bold">
                        {{ t('admin.products.sections.organization') }}
                    </h2>
                    <CategoriesField
                        v-model:ids="fields.categoryIds"
                        v-model:primary-id="fields.primaryCategoryId"
                        :tree="tree"
                        :error="form.errors.category_ids ?? form.errors.primary_category_id"
                    />
                    <div>
                        <label for="product-brand" class="mb-2 block text-sm font-semibold">
                            {{ t('admin.products.brand') }}
                            <span class="font-normal text-fg-muted">{{ t('common.optional') }}</span>
                        </label>
                        <select
                            id="product-brand"
                            :value="fields.brandId === null ? '' : String(fields.brandId)"
                            :class="selectClass"
                            @change="
                                fields.brandId =
                                    ($event.target as HTMLSelectElement).value === ''
                                        ? null
                                        : Number(($event.target as HTMLSelectElement).value)
                            "
                        >
                            <option value="">{{ t('admin.products.noBrand') }}</option>
                            <option v-for="brand in brands" :key="brand.id" :value="String(brand.id)">
                                {{ brand.name }}{{ brand.persian_name ? ` (${brand.persian_name})` : '' }}
                            </option>
                        </select>
                    </div>
                    <TagsInput v-model="fields.tags" />
                </section>

                <section id="pricing" :class="cardClass" aria-labelledby="pricing-title">
                    <h2 id="pricing-title" class="text-lg font-bold">{{ t('admin.products.sections.pricing') }}</h2>
                    <div class="flex flex-col gap-2">
                        <span class="text-sm font-semibold">{{ t('admin.products.stock.title') }}</span>
                        <div class="sm:w-96">
                            <SegmentedControl
                                v-model="stockMode"
                                :label="t('admin.products.stock.title')"
                                :options="stockModes"
                            />
                        </div>
                        <p class="text-[13px] leading-relaxed text-fg-muted">
                            {{ t(`admin.products.stock.${stockMode}Hint`) }}
                        </p>
                    </div>
                    <div v-if="fields.trackStock" class="grid gap-4 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-line p-3.5">
                            <input
                                v-model="fields.allowBackorders"
                                type="checkbox"
                                class="mt-1 size-4 accent-[var(--color-primary)]"
                            />
                            <span class="flex flex-col gap-0.5">
                                <span class="text-sm font-semibold">{{ t('admin.products.stock.backorders') }}</span>
                                <span class="text-[13px] text-fg-muted">{{
                                    t('admin.products.stock.backordersHint')
                                }}</span>
                            </span>
                        </label>
                        <NumberField
                            v-model="fields.lowStockThreshold"
                            :label="t('admin.products.stock.lowThreshold')"
                            :hint="
                                t('admin.products.stock.lowThresholdHint', {
                                    n: product?.low_stock_threshold_effective ?? '',
                                })
                            "
                            optional
                            :error="form.errors.low_stock_threshold"
                        />
                    </div>
                    <VariantsEditor
                        v-model:type="fields.type"
                        v-model:chosen="chosen"
                        v-model:rows="rows"
                        :options="options"
                        :images="images"
                        :track-stock="fields.trackStock"
                        :sku-base="fields.slug"
                        :errors="fieldErrors"
                    />
                </section>

                <section id="images" :class="cardClass" aria-labelledby="images-title">
                    <h2 id="images-title" class="text-lg font-bold">{{ t('admin.products.sections.images') }}</h2>
                    <ImagesManager v-model="images" />
                </section>

                <section id="specifications" :class="cardClass" aria-labelledby="specifications-title">
                    <h2 id="specifications-title" class="text-lg font-bold">
                        {{ t('admin.products.sections.specifications') }}
                    </h2>
                    <p class="text-[13px] leading-relaxed text-fg-muted">{{ t('admin.products.specsHint') }}</p>
                    <SpecificationsEditor
                        v-model="specValues"
                        :attributes="attributes"
                        :loading="attributesLoading"
                        :errors="specErrors"
                    />
                </section>

                <section id="shipping" :class="cardClass" aria-labelledby="shipping-title">
                    <h2 id="shipping-title" class="text-lg font-bold">{{ t('admin.products.sections.shipping') }}</h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <NumberField
                            v-model="fields.weightGrams"
                            :label="t('admin.products.shipping.weight')"
                            :unit="t('admin.products.shipping.grams')"
                            optional
                            :error="form.errors.weight_grams"
                        />
                        <NumberField
                            v-model="fields.lengthMm"
                            :label="t('admin.products.shipping.length')"
                            :unit="t('admin.products.shipping.mm')"
                            optional
                            :error="form.errors.length_mm"
                        />
                        <NumberField
                            v-model="fields.widthMm"
                            :label="t('admin.products.shipping.width')"
                            :unit="t('admin.products.shipping.mm')"
                            optional
                            :error="form.errors.width_mm"
                        />
                        <NumberField
                            v-model="fields.heightMm"
                            :label="t('admin.products.shipping.height')"
                            :unit="t('admin.products.shipping.mm')"
                            optional
                            :error="form.errors.height_mm"
                        />
                    </div>
                </section>

                <section id="seo" class="scroll-mt-24">
                    <SeoPanel
                        v-model:keyphrase="fields.focusKeyphrase"
                        v-model:meta-title="fields.metaTitle"
                        v-model:meta-description="fields.metaDescription"
                        type="product"
                        :name="fields.name"
                        :slug="fields.slug"
                        :description="htmlToText(fields.description)"
                        :has-image="images.length > 0"
                        :subject-id="product?.id ?? null"
                        :path="primaryPath"
                        :keyphrase-used-by="[]"
                        :errors="{
                            focus_keyphrase: form.errors.focus_keyphrase,
                            meta_title: form.errors.meta_title,
                            meta_description: form.errors.meta_description,
                        }"
                    />
                </section>
            </form>
        </div>

        <!-- Sticky save bar: status, publish date and Save -->
        <div
            v-if="!loading && !loadError && canManage"
            class="sticky bottom-0 z-30 -mx-4 border-t border-line bg-surface/95 px-4 py-3 shadow-[0_-4px_16px_rgb(0_0_0/0.06)] backdrop-blur sm:mx-0 sm:rounded-t-2xl sm:border-x"
        >
            <div class="flex flex-wrap items-center gap-3">
                <div class="w-full sm:w-80">
                    <SegmentedControl
                        v-model="fields.status"
                        :label="t('admin.products.statusLabel')"
                        :options="statusOptions"
                    />
                </div>
                <div v-if="fields.status === 'published'" class="flex items-center gap-2">
                    <label for="product-published-at" class="text-xs text-fg-muted">{{
                        t('admin.products.publishAt')
                    }}</label>
                    <input
                        id="product-published-at"
                        v-model="fields.publishedAt"
                        type="datetime-local"
                        class="h-10 rounded-lg border border-line-strong bg-surface px-2.5 text-sm text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft"
                    />
                    <AppBadge v-if="isScheduled" tone="warning">
                        {{
                            t('admin.products.scheduledFor', {
                                date: formatDateTime(fromLocalInput(fields.publishedAt) ?? ''),
                            })
                        }}
                    </AppBadge>
                </div>
                <span class="grow" />
                <span v-if="dirty" class="text-xs text-warning">{{ t('admin.products.unsaved') }}</span>
                <AppButton type="submit" form="product-form" size="md" :loading="form.processing.value">
                    {{ product ? t('admin.products.save') : t('admin.products.create') }}
                </AppButton>
            </div>
        </div>

        <StockHistoryDialog
            v-if="product"
            :open="stockHistoryOpen"
            :product-id="product.id"
            @close="stockHistoryOpen = false"
        />

        <AppDialog
            :open="confirmDelete"
            :title="t('admin.products.deleteTitle', { name: product?.name ?? '' })"
            icon="trash"
            tone="danger"
            @close="confirmDelete = false"
        >
            <p class="text-sm leading-loose">{{ t('admin.products.deleteText') }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" @click="remove">{{
                    t('admin.products.deleteConfirm')
                }}</AppButton>
                <AppButton variant="secondary" size="md" @click="confirmDelete = false">{{
                    t('common.cancel')
                }}</AppButton>
            </template>
        </AppDialog>

        <AppDialog
            :open="leaveTarget !== null"
            :title="t('admin.products.leaveTitle')"
            icon="alert"
            tone="danger"
            @close="stay"
        >
            <p class="text-sm leading-loose">{{ t('admin.products.leaveText') }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" @click="leave">{{ t('admin.products.leaveConfirm') }}</AppButton>
                <AppButton variant="secondary" size="md" @click="stay">{{ t('admin.products.leaveStay') }}</AppButton>
            </template>
        </AppDialog>
    </div>
</template>
