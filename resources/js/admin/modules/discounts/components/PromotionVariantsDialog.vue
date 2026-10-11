<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { toApiError } from '@/shared/http/errors';
import type { Paginated } from '@/shared/types/api';
import AppBadge from '@/shared/ui/AppBadge.vue';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppPagination from '@/shared/ui/AppPagination.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import NumberField from '@/shared/ui/NumberField.vue';
import SearchInput from '@/shared/ui/SearchInput.vue';
import SelectField from '@/shared/ui/SelectField.vue';
import { brandsApi } from '../../brands/api';
import { categoriesApi } from '../../categories/api';
import { flatten } from '../../categories/tree';
import { toLocalInput } from '../../products/variants';
import type { ProductFilters, ProductStatus } from '../../products/types';
import { useMoney } from '../../settings/store';
import { promotionsApi } from '../api';
import { MAX_PER_ORDER, MAX_QUANTITY, type NewRowDefaults } from '../promotions';
import { MAX_PERCENT } from '../saleEvents';
import type { PromotionPickerProduct } from '../types';

/** A chosen variant with its product. */
export interface ChosenVariant {
    product: PromotionPickerProduct;
    variant: PromotionPickerProduct['variants'][number];
}

/**
 * "Add product" on the promotions page: every SKU, grouped by product, 20 products per page, with
 * search (name or SKU) and filters (brand, category with its sub-categories, status, availability),
 * published products and in-stock variants by default. The selection is kept across pages and
 * filters. Starting values (percentage, limits, dates) go to every chosen variant.
 */
const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: []; add: [chosen: ChosenVariant[], defaults: NewRowDefaults] }>();

const PER_PAGE = 20;

const { t } = useI18n();
const { formatNumber } = useLocale();
const money = useMoney();

// ---- filters ----

type Filters = { q: string; brand: string; category: string; status: '' | ProductStatus; stock: '' | 'in' | 'out' };
/** By default only what can be sold now: published products, variants in stock. */
const DEFAULTS: Filters = { q: '', brand: '', category: '', status: 'published', stock: 'in' };
const filters = ref<Filters>({ ...DEFAULTS });
const page = ref(1);

const brandOptions = ref<{ value: string; label: string }[]>([]);
const categoryOptions = ref<{ value: string; label: string }[]>([]);
const statusOptions = computed(() => [
    { value: '' as const, label: t('admin.discounts.all') },
    ...(['published', 'draft', 'archived'] as const).map((value) => ({
        value,
        label: t(`admin.products.status.${value}`),
    })),
]);
const stockOptions = computed(() => [
    { value: '' as const, label: t('admin.discounts.all') },
    { value: 'in' as const, label: t('admin.products.picker.inStock') },
    { value: 'out' as const, label: t('admin.products.picker.outOfStock') },
]);

const query = computed<ProductFilters>(() => ({
    q: filters.value.q || undefined,
    brand_id: filters.value.brand ? Number(filters.value.brand) : undefined,
    category_id: filters.value.category ? Number(filters.value.category) : undefined,
    status: filters.value.status || undefined,
    stock: filters.value.stock || undefined,
}));
const changedFilters = computed(() => JSON.stringify(filters.value) !== JSON.stringify(DEFAULTS));

// ---- loading ----

const result = ref<Paginated<PromotionPickerProduct> | null>(null);
const loading = ref(false);
const loadError = ref<string | null>(null);

async function loadChoices(): Promise<void> {
    const [brands, tree] = await Promise.all([brandsApi.list(), categoriesApi.tree('product')]);
    brandOptions.value = [
        { value: '', label: t('admin.discounts.all') },
        ...brands.map((brand) => ({ value: String(brand.id), label: brand.name })),
    ];
    categoryOptions.value = [
        { value: '', label: t('admin.discounts.all') },
        ...flatten(tree).map((item) => ({
            value: String(item.node.id),
            label: `${'— '.repeat(item.depth)}${item.node.name}`,
        })),
    ];
}

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;

    try {
        result.value = await promotionsApi.variants({ ...query.value, page: page.value, per_page: PER_PAGE });
    } catch (caught) {
        loadError.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

// ---- starting values ----

const percent = ref<number | null>(null);
const maxPerOrder = ref<number | null>(null);
const quantityLimit = ref<number | null>(null);
const startsAt = ref('');
const endsAt = ref('');

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        filters.value = { ...DEFAULTS };
        page.value = 1;
        selected.value = new Map();
        percent.value = null;
        maxPerOrder.value = null;
        quantityLimit.value = null;
        // From now for a week.
        startsAt.value = toLocalInput(new Date().toISOString());
        endsAt.value = toLocalInput(new Date(Date.now() + 7 * 24 * 3600 * 1000).toISOString());
        void load();

        if (brandOptions.value.length === 0) {
            loadChoices().catch((caught) => (loadError.value = toApiError(caught).message));
        }
    },
);

// Compared as text: resetting the filters to what they were is not a change.
watch(
    () => JSON.stringify(query.value),
    () => {
        if (props.open) {
            page.value = 1;
            void load();
        }
    },
);

function goTo(next: number): void {
    page.value = next;
    void load();
}

// ---- selection (kept across pages and filters) ----

const selected = ref<Map<number, ChosenVariant>>(new Map());
const pageVariants = computed<ChosenVariant[]>(() =>
    (result.value?.data ?? []).flatMap((product) => product.variants.map((variant) => ({ product, variant }))),
);
const allOn = (items: ChosenVariant[]): boolean =>
    items.length > 0 && items.every((item) => selected.value.has(item.variant.id));

function setOn(items: ChosenVariant[], on: boolean): void {
    const next = new Map(selected.value);
    items.forEach((item) => (on ? next.set(item.variant.id, item) : next.delete(item.variant.id)));
    selected.value = next;
}

const productItems = (product: PromotionPickerProduct): ChosenVariant[] =>
    product.variants.map((variant) => ({ product, variant }));

/** Every variant of every product the filters match (all pages), once loaded; reset when the filters change. */
const allMatching = ref<ChosenVariant[] | null>(null);
const loadingAll = ref(false);
/** Products fetched per request when selecting everything that matches. */
const ALL_PER_PAGE = 100;

watch(
    () => JSON.stringify(query.value),
    () => (allMatching.value = null),
);

/** Selects (or, when all are selected, unselects) every product the filters match with all its variants. */
async function toggleAllMatching(): Promise<void> {
    if (allMatching.value !== null && allOn(allMatching.value)) {
        setOn(allMatching.value, false);

        return;
    }

    loadingAll.value = true;
    loadError.value = null;

    try {
        if (allMatching.value === null) {
            const items: ChosenVariant[] = [];

            for (let next = 1; ; next++) {
                const chunk = await promotionsApi.variants({ ...query.value, page: next, per_page: ALL_PER_PAGE });
                chunk.data.forEach((product) => items.push(...productItems(product)));

                if (next >= chunk.meta.last_page) {
                    break;
                }
            }

            allMatching.value = items;
        }

        setOn(allMatching.value, true);
    } catch (caught) {
        loadError.value = toApiError(caught).message;
    } finally {
        loadingAll.value = false;
    }
}

// ---- adding ----

const outOfRange = (value: number | null, max: number): boolean => value !== null && (value < 1 || value > max);
const problems = computed(() => ({
    percent: percent.value !== null && (percent.value < 1 || percent.value > MAX_PERCENT),
    maxPerOrder: outOfRange(maxPerOrder.value, MAX_PER_ORDER),
    quantityLimit: outOfRange(quantityLimit.value, MAX_QUANTITY),
    dates: startsAt.value === '' || endsAt.value === '' || endsAt.value <= startsAt.value,
}));
const canAdd = computed(() => selected.value.size > 0 && !Object.values(problems.value).some(Boolean));

function add(): void {
    if (!canAdd.value) {
        return;
    }

    emit('add', [...selected.value.values()], {
        percent: percent.value,
        maxPerOrder: maxPerOrder.value,
        quantityLimit: quantityLimit.value,
        startsAt: startsAt.value,
        endsAt: endsAt.value,
    });
}

// ---- display ----

function stockText(variant: PromotionPickerProduct['variants'][number]): string {
    if (!variant.in_stock) {
        return t('admin.products.picker.outOfStock');
    }

    return variant.stock_quantity === null
        ? t('admin.discounts.promotions.picker.available')
        : t('admin.discounts.promotions.picker.inStock', { n: formatNumber(variant.stock_quantity) });
}

const statusTone = (status: ProductStatus): 'success' | 'neutral' => (status === 'published' ? 'success' : 'neutral');
const dateClass =
    'h-11 w-full rounded-lg border border-line-strong bg-surface px-3 text-sm text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft';
</script>

<template>
    <AppDialog
        :open="open"
        :title="t('admin.discounts.promotions.picker.title')"
        icon="package"
        size="lg"
        @close="emit('close')"
    >
        <div class="flex flex-col gap-4">
            <p class="text-sm leading-relaxed text-fg-muted">{{ t('admin.discounts.promotions.picker.intro') }}</p>

            <div class="flex flex-col gap-2.5">
                <SearchInput v-model="filters.q" :label="t('admin.products.picker.search')" />
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <SelectField
                        v-model="filters.status"
                        :label="t('admin.products.picker.status')"
                        :options="statusOptions"
                    />
                    <SelectField
                        v-model="filters.stock"
                        :label="t('admin.products.picker.availability')"
                        :options="stockOptions"
                    />
                    <SelectField
                        v-model="filters.category"
                        :label="t('admin.products.picker.category')"
                        :options="categoryOptions"
                    />
                    <SelectField
                        v-model="filters.brand"
                        :label="t('admin.products.picker.brand')"
                        :options="brandOptions"
                    />
                </div>
            </div>

            <FormAlert v-if="loadError" :message="loadError" />

            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-[13px]">
                <label v-if="pageVariants.length > 0" class="flex cursor-pointer items-center gap-2 font-semibold">
                    <input
                        type="checkbox"
                        class="size-4 accent-[var(--color-primary)]"
                        :checked="allOn(pageVariants)"
                        @change="setOn(pageVariants, !allOn(pageVariants))"
                    />
                    {{ t('admin.products.picker.selectPage') }}
                </label>
                <label
                    v-if="result && result.meta.total > 0"
                    :class="['flex cursor-pointer items-center gap-2 font-semibold', loadingAll && 'opacity-60']"
                >
                    <input
                        type="checkbox"
                        class="size-4 accent-[var(--color-primary)]"
                        :checked="allMatching !== null && allOn(allMatching)"
                        :disabled="loadingAll"
                        @change="toggleAllMatching"
                    />
                    {{ t('admin.discounts.promotions.picker.selectAll', { n: formatNumber(result.meta.total) }) }}
                </label>
                <AppSpinner v-if="loadingAll" />
                <span v-if="selected.size > 0" class="text-fg-muted">{{
                    t('admin.discounts.promotions.picker.selectedCount', {
                        products: formatNumber(new Set([...selected.values()].map((item) => item.product.id)).size),
                        variants: formatNumber(selected.size),
                    })
                }}</span>
                <span class="grow" />
                <button
                    v-if="changedFilters"
                    type="button"
                    class="rounded text-fg-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    @click="filters = { ...DEFAULTS }"
                >
                    {{ t('admin.discounts.promotions.picker.resetFilters') }}
                </button>
            </div>

            <div class="rounded-xl border border-line">
                <div v-if="!result" class="flex justify-center py-10"><AppSpinner /></div>
                <p v-else-if="result.data.length === 0" class="px-4 py-8 text-center text-sm text-fg-muted">
                    {{ t('admin.products.picker.noMatches') }}
                </p>
                <ul v-else :aria-busy="loading" :class="['divide-y divide-line', loading && 'opacity-60']">
                    <li v-for="product in result.data" :key="product.id">
                        <div class="flex items-center gap-3 bg-surface-muted/40 px-3.5 py-2.5">
                            <input
                                type="checkbox"
                                class="size-4 shrink-0 accent-[var(--color-primary)]"
                                :checked="allOn(productItems(product))"
                                :disabled="product.variants.length === 0"
                                :aria-label="t('admin.discounts.promotions.selectProduct', { name: product.name })"
                                @change="setOn(productItems(product), !allOn(productItems(product)))"
                            />
                            <img
                                v-if="product.image"
                                :src="product.image"
                                alt=""
                                class="size-9 shrink-0 rounded-lg object-cover"
                            />
                            <span
                                v-else
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-surface text-fg-muted"
                                ><AppIcon name="image" :size="16"
                            /></span>
                            <span class="flex min-w-0 grow flex-col">
                                <span class="truncate text-sm font-semibold">{{ product.name }}</span>
                                <span class="text-xs text-fg-muted">{{
                                    product.type === 'simple'
                                        ? t('admin.discounts.promotions.simple')
                                        : t('admin.discounts.promotions.picker.variants', {
                                              n: formatNumber(product.variants.length),
                                          })
                                }}</span>
                            </span>
                            <AppBadge :tone="statusTone(product.status)" class="shrink-0">{{
                                t(`admin.products.status.${product.status}`)
                            }}</AppBadge>
                        </div>
                        <p v-if="product.variants.length === 0" class="px-3.5 py-2 ps-12 text-xs text-fg-muted">
                            {{ t('admin.discounts.promotions.picker.noVariants') }}
                        </p>
                        <label
                            v-for="variant in product.variants"
                            :key="variant.id"
                            :class="[
                                'flex cursor-pointer flex-wrap items-center gap-x-4 gap-y-1 py-2 ps-12 pe-3.5 hover:bg-surface-muted/50',
                                selected.has(variant.id) && 'bg-primary-soft/40',
                            ]"
                        >
                            <input
                                type="checkbox"
                                class="size-4 shrink-0 accent-[var(--color-primary)]"
                                :checked="selected.has(variant.id)"
                                @change="setOn([{ product, variant }], !selected.has(variant.id))"
                            />
                            <span class="min-w-16 text-sm font-medium">{{
                                variant.label || t('admin.discounts.promotions.defaultVariant')
                            }}</span>
                            <span class="min-w-32 text-xs text-fg-muted" dir="ltr">{{ variant.sku }}</span>
                            <span class="text-sm">{{ money.format(variant.price) }}</span>
                            <span :class="['text-xs', variant.in_stock ? 'text-fg-muted' : 'text-danger']">{{
                                stockText(variant)
                            }}</span>
                            <AppBadge v-if="variant.has_promotion" tone="warning">{{
                                t('admin.discounts.promotions.picker.hasPromotion')
                            }}</AppBadge>
                        </label>
                    </li>
                </ul>
                <AppPagination v-if="result && result.meta.last_page > 1" :meta="result.meta" @change="goTo" />
            </div>

            <section class="flex flex-col gap-3 rounded-xl bg-surface-muted/50 p-3.5">
                <p class="text-[13px] font-semibold text-fg-muted">
                    {{ t('admin.discounts.promotions.picker.defaults') }}
                </p>
                <div class="grid gap-3 sm:grid-cols-3">
                    <NumberField
                        v-model="percent"
                        :label="t('admin.discounts.promotions.columns.percent')"
                        unit="%"
                        :error="problems.percent ? t('admin.discounts.promotions.problems.percent_range') : undefined"
                        optional
                    />
                    <NumberField
                        v-model="maxPerOrder"
                        :label="t('admin.discounts.promotions.columns.maxPerOrder')"
                        :placeholder="t('admin.discounts.promotions.noLimit')"
                        :unit="t('admin.discounts.promotions.unit')"
                        :error="problems.maxPerOrder ? t('admin.discounts.promotions.problems.limit_range') : undefined"
                        optional
                    />
                    <NumberField
                        v-model="quantityLimit"
                        :label="t('admin.discounts.promotions.columns.quantity')"
                        :placeholder="t('admin.discounts.promotions.unlimited')"
                        :unit="t('admin.discounts.promotions.unit')"
                        :error="
                            problems.quantityLimit ? t('admin.discounts.promotions.problems.quantity_range') : undefined
                        "
                        optional
                    />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="promotion-add-start" class="mb-1.5 block text-xs font-semibold text-fg-muted">{{
                            t('admin.discounts.promotions.columns.startsAt')
                        }}</label>
                        <input id="promotion-add-start" v-model="startsAt" type="datetime-local" :class="dateClass" />
                    </div>
                    <div>
                        <label for="promotion-add-end" class="mb-1.5 block text-xs font-semibold text-fg-muted">{{
                            t('admin.discounts.promotions.columns.endsAt')
                        }}</label>
                        <input id="promotion-add-end" v-model="endsAt" type="datetime-local" :class="dateClass" />
                    </div>
                </div>
                <p v-if="problems.dates" class="text-[13px] text-danger">
                    {{ t('admin.discounts.promotions.problems.dates_order') }}
                </p>
            </section>
        </div>

        <template #footer>
            <AppButton size="md" :disabled="!canAdd" @click="add">{{
                t('admin.discounts.promotions.picker.add', { n: formatNumber(selected.size) })
            }}</AppButton>
            <AppButton v-if="selected.size > 0" variant="secondary" size="md" @click="selected = new Map()">{{
                t('admin.products.picker.clear')
            }}</AppButton>
            <AppButton variant="secondary" size="md" @click="emit('close')">{{ t('common.cancel') }}</AppButton>
        </template>
    </AppDialog>
</template>
