<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import AppIcon from '@/shared/ui/AppIcon.vue';
import { useMoney } from '../../settings/store';
import {
    discountPercent,
    isEdited,
    problems,
    stockLevel,
    valueOf,
    type BulkProduct,
    type BulkVariant,
    type EditableField,
    type PageEdits,
} from '../bulkEdit';
import BulkNumberInput from './BulkNumberInput.vue';

/**
 * One product in the bulk editor: a header (image, name, status, summary) and its variants as
 * rows of editable values. Changed values show the previous one; errors show under the field.
 */
const props = defineProps<{
    product: BulkProduct;
    edits: PageEdits;
    serverErrors: Record<number, Partial<Record<EditableField, string>>>;
    saved: Set<number>;
    /** Row position of the first variant on the page (Enter moves down a column). */
    firstIndex: number;
}>();

const emit = defineEmits<{ change: [variant: BulkVariant, field: EditableField, value: number | boolean | null] }>();

const { t } = useI18n();
const { formatNumber, formatDate } = useLocale();
const { rialsPerUnit, unit, format: formatMoney } = useMoney();

const collapsed = ref(false);
/** Variants whose empty sale field was opened with "+ Sale". */
const openedSale = ref<Set<number>>(new Set());

const isVariable = computed(() => props.product.type === 'variable');
const changedCount = computed(
    () => props.product.variants.filter((variant) => isEdited(props.edits, variant.id)).length,
);

function numberOf(variant: BulkVariant, field: 'price' | 'sale_price' | 'stock_quantity'): number | null {
    const value = valueOf(props.edits, variant, field);

    return typeof value === 'number' ? value : null;
}

const enabled = (variant: BulkVariant): boolean => valueOf(props.edits, variant, 'is_enabled') === true;

function errorOf(variant: BulkVariant, field: EditableField): string | undefined {
    const problem = problems(props.edits, variant)[field];

    return problem ? t(`admin.bulkEdit.problems.${problem}`) : props.serverErrors[variant.id]?.[field];
}

/** "Was 200,000" under a changed value. */
function previous(variant: BulkVariant, field: 'price' | 'sale_price' | 'stock_quantity'): string | null {
    if (!isEdited(props.edits, variant.id, field)) {
        return null;
    }

    const before = variant[field];
    const shown =
        before === null
            ? t('admin.bulkEdit.none')
            : field === 'stock_quantity'
              ? formatNumber(before)
              : formatNumber(before / rialsPerUnit.value);

    return t('admin.bulkEdit.was', { value: shown });
}

const summary = computed(() => {
    const prices = props.product.variants.map((variant) => numberOf(variant, 'price') ?? 0);
    const min = Math.min(...prices);
    const max = Math.max(...prices);
    const price =
        min === max
            ? formatMoney(min)
            : t('admin.products.priceRange', { min: formatMoney(min), max: formatMoney(max) });
    const stock = props.product.track_stock
        ? t('admin.bulkEdit.stockTotal', {
              n: formatNumber(
                  props.product.variants.reduce((sum, variant) => sum + (numberOf(variant, 'stock_quantity') ?? 0), 0),
              ),
          })
        : t('admin.bulkEdit.notTracked');

    return `${price} · ${stock}`;
});

const statusClass: Record<BulkProduct['status'], string> = {
    published: 'bg-success-soft text-success',
    draft: 'bg-surface-muted text-fg-muted',
    archived: 'bg-surface-muted text-fg-muted',
};

const label = (variant: BulkVariant, field: string): string =>
    t('admin.bulkEdit.fieldLabel', {
        field: t(`admin.bulkEdit.columns.${field}`),
        name: variant.label ? `${props.product.name} (${variant.label})` : props.product.name,
    });

function showSale(variant: BulkVariant): boolean {
    return (
        numberOf(variant, 'sale_price') !== null || openedSale.value.has(variant.id) || !!errorOf(variant, 'sale_price')
    );
}

async function openSale(variant: BulkVariant): Promise<void> {
    openedSale.value = new Set(openedSale.value).add(variant.id);
    await nextTick();
    document.querySelector<HTMLInputElement>(`[data-sale="${variant.id}"] input`)?.focus();
}

function clearSale(variant: BulkVariant): void {
    const next = new Set(openedSale.value);
    next.delete(variant.id);
    openedSale.value = next;
    emit('change', variant, 'sale_price', null);
}

function step(variant: BulkVariant, by: 1 | -1): void {
    emit('change', variant, 'stock_quantity', Math.max(0, (numberOf(variant, 'stock_quantity') ?? 0) + by));
}

const dotClass: Record<string, string> = {
    out: 'bg-danger',
    low: 'bg-warning',
    ok: 'bg-success',
    untracked: 'bg-line-strong',
};
</script>

<template>
    <article
        :class="[
            'overflow-hidden rounded-2xl border bg-surface shadow-[0_1px_2px_rgb(0_0_0/0.04)] transition-colors',
            changedCount ? 'border-warning/50' : 'border-line',
        ]"
        :aria-label="product.name"
    >
        <!-- Header -->
        <header class="flex items-center gap-3 px-4 py-3">
            <span
                class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-surface-muted text-fg-muted"
            >
                <img v-if="product.image" :src="product.image" alt="" class="size-full object-contain" />
                <AppIcon v-else name="package" :size="20" />
            </span>
            <div class="flex min-w-0 grow flex-col gap-0.5">
                <div class="flex min-w-0 items-center gap-2">
                    <RouterLink
                        :to="{ name: 'product', params: { id: product.id } }"
                        :title="product.name"
                        class="truncate rounded font-semibold text-fg hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    >
                        {{ product.name }}
                    </RouterLink>
                    <span
                        :class="[
                            'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold',
                            statusClass[product.status],
                        ]"
                        >{{ t(`admin.products.status.${product.status}`) }}</span
                    >
                </div>
                <p class="truncate text-xs text-fg-muted">
                    <template v-if="isVariable"
                        >{{ t('admin.products.variantsCount', { n: formatNumber(product.variants.length) }) }} ·
                    </template>
                    {{ summary }}
                </p>
            </div>
            <span
                v-if="changedCount"
                class="hidden shrink-0 items-center gap-1.5 rounded-full bg-warning-soft px-2.5 py-1 text-xs font-semibold text-warning sm:flex"
            >
                <span class="size-1.5 rounded-full bg-warning" aria-hidden="true" />
                {{ t('admin.bulkEdit.changedVariants', { n: formatNumber(changedCount) }) }}
            </span>
            <button
                v-if="isVariable"
                type="button"
                :aria-expanded="!collapsed"
                :aria-label="collapsed ? t('admin.bulkEdit.expand') : t('admin.bulkEdit.collapse')"
                class="flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                @click="collapsed = !collapsed"
            >
                <AppIcon
                    name="chevronDown"
                    :size="18"
                    :class="[
                        'transition-transform motion-reduce:transition-none',
                        collapsed && '-rotate-90 rtl:rotate-90',
                    ]"
                />
            </button>
        </header>

        <!-- Variants -->
        <div v-show="!collapsed" class="border-t border-line">
            <div
                v-for="(variant, position) in product.variants"
                :key="variant.id"
                :class="[
                    'relative grid grid-cols-2 items-start gap-x-3 gap-y-2 px-4 py-3 sm:grid-cols-4 xl:grid-cols-[minmax(0,1fr)_8rem_11rem_12rem_9.5rem_4rem] xl:gap-y-0',
                    position > 0 && 'border-t border-line/70',
                    isEdited(edits, variant.id) && 'bg-warning-soft/30',
                    !enabled(variant) && 'opacity-70',
                ]"
            >
                <!-- Changed rows get an accent bar on the leading edge -->
                <span
                    v-if="isEdited(edits, variant.id)"
                    class="absolute inset-y-2 start-0 w-1 rounded-e-full bg-warning"
                    aria-hidden="true"
                />

                <!-- Variant -->
                <div class="col-span-2 flex min-h-10 items-center gap-2.5 sm:col-span-4 xl:col-span-1">
                    <!-- The variant's own image, else the product's main image -->
                    <span
                        class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-surface-muted text-fg-muted"
                    >
                        <img
                            v-if="variant.image ?? product.image"
                            :src="variant.image ?? product.image ?? ''"
                            alt=""
                            class="size-full object-contain"
                            loading="lazy"
                        />
                        <AppIcon v-else name="image" :size="16" />
                    </span>
                    <span
                        v-if="isVariable"
                        class="rounded-lg bg-surface-muted px-2.5 py-1 text-sm font-semibold text-fg"
                        >{{ variant.label }}</span
                    >
                    <span v-else class="text-sm text-fg-muted">{{ t('admin.bulkEdit.simpleProduct') }}</span>
                    <span
                        v-if="saved.has(variant.id)"
                        class="flex items-center gap-1 rounded-full bg-success-soft px-2 py-0.5 text-xs font-semibold text-success"
                    >
                        <AppIcon name="check" :size="12" />
                        {{ t('admin.bulkEdit.savedRow') }}
                    </span>
                    <span class="truncate text-xs text-fg-muted xl:hidden" dir="ltr">{{ variant.sku }}</span>
                </div>

                <!-- SKU -->
                <div class="hidden min-h-10 items-center xl:flex">
                    <span class="truncate font-mono text-xs text-fg-muted" dir="ltr" :title="variant.sku">{{
                        variant.sku
                    }}</span>
                </div>

                <!-- Price -->
                <div data-field="price" :data-index="firstIndex + position">
                    <span class="mb-1 block text-xs text-fg-muted xl:sr-only">{{
                        `${t('admin.bulkEdit.columns.price')} (${unit})`
                    }}</span>
                    <BulkNumberInput
                        :model-value="numberOf(variant, 'price')"
                        :label="label(variant, 'price')"
                        :factor="rialsPerUnit"
                        :suffix="unit"
                        :invalid="!!errorOf(variant, 'price')"
                        :changed="isEdited(edits, variant.id, 'price')"
                        @update:model-value="emit('change', variant, 'price', $event)"
                    />
                    <p v-if="errorOf(variant, 'price')" class="mt-1 text-xs text-danger">
                        {{ errorOf(variant, 'price') }}
                    </p>
                    <p v-else-if="previous(variant, 'price')" class="mt-1 text-xs text-fg-muted line-through">
                        {{ previous(variant, 'price') }}
                    </p>
                </div>

                <!-- Sale price -->
                <div data-field="sale_price" :data-index="firstIndex + position" :data-sale="variant.id">
                    <span class="mb-1 block text-xs text-fg-muted xl:sr-only">{{
                        `${t('admin.bulkEdit.columns.sale')} (${unit})`
                    }}</span>
                    <div v-if="showSale(variant)" class="flex items-center gap-1">
                        <div class="min-w-0 grow">
                            <BulkNumberInput
                                :model-value="numberOf(variant, 'sale_price')"
                                :label="label(variant, 'sale')"
                                :factor="rialsPerUnit"
                                :suffix="unit"
                                :invalid="!!errorOf(variant, 'sale_price')"
                                :changed="isEdited(edits, variant.id, 'sale_price')"
                                @update:model-value="emit('change', variant, 'sale_price', $event)"
                            />
                        </div>
                        <button
                            type="button"
                            tabindex="-1"
                            :aria-label="t('admin.bulkEdit.removeSale')"
                            :title="t('admin.bulkEdit.removeSale')"
                            class="flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-fg-muted hover:bg-danger-soft hover:text-danger"
                            @click="clearSale(variant)"
                        >
                            <AppIcon name="close" :size="14" />
                        </button>
                    </div>
                    <button
                        v-else
                        type="button"
                        class="flex h-10 w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-dashed border-line-strong text-xs font-medium text-fg-muted transition-colors hover:border-primary hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                        @click="openSale(variant)"
                    >
                        <AppIcon name="plus" :size="14" />
                        {{ t('admin.bulkEdit.addSale') }}
                    </button>
                    <p v-if="errorOf(variant, 'sale_price')" class="mt-1 text-xs text-danger">
                        {{ errorOf(variant, 'sale_price') }}
                    </p>
                    <p
                        v-else-if="
                            discountPercent(numberOf(variant, 'price'), numberOf(variant, 'sale_price')) !== null
                        "
                        class="mt-1 flex flex-wrap items-center gap-1.5 text-xs"
                    >
                        <span class="rounded-full bg-danger px-1.5 py-px font-bold text-on-danger">
                            {{
                                t('admin.bulkEdit.discount', {
                                    n: formatNumber(
                                        discountPercent(numberOf(variant, 'price'), numberOf(variant, 'sale_price')) ??
                                            0,
                                    ),
                                })
                            }}
                        </span>
                        <span v-if="variant.sale_ends_at" class="text-fg-muted">{{
                            t('admin.bulkEdit.saleEnds', { date: formatDate(variant.sale_ends_at) })
                        }}</span>
                    </p>
                </div>

                <!-- Stock -->
                <div>
                    <span class="mb-1 block text-xs text-fg-muted xl:sr-only">{{
                        t('admin.bulkEdit.columns.stock')
                    }}</span>
                    <div
                        v-if="product.track_stock"
                        data-field="stock_quantity"
                        :data-index="firstIndex + position"
                        class="flex items-center gap-1"
                    >
                        <button
                            type="button"
                            tabindex="-1"
                            :aria-label="t('admin.bulkEdit.decrease')"
                            class="flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-lg border border-line text-fg-muted hover:border-line-strong hover:text-fg disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="(numberOf(variant, 'stock_quantity') ?? 0) <= 0"
                            @click="step(variant, -1)"
                        >
                            <span aria-hidden="true" class="text-base leading-none">−</span>
                        </button>
                        <div class="relative min-w-0 grow">
                            <BulkNumberInput
                                :model-value="numberOf(variant, 'stock_quantity')"
                                :label="label(variant, 'stock')"
                                :invalid="!!errorOf(variant, 'stock_quantity')"
                                :changed="isEdited(edits, variant.id, 'stock_quantity')"
                                @update:model-value="emit('change', variant, 'stock_quantity', $event)"
                            />
                            <span
                                :class="[
                                    'pointer-events-none absolute end-2 top-1/2 size-2 -translate-y-1/2 rounded-full',
                                    dotClass[stockLevel(product, numberOf(variant, 'stock_quantity'))],
                                ]"
                                :title="
                                    t(
                                        `admin.bulkEdit.stockLevels.${stockLevel(product, numberOf(variant, 'stock_quantity'))}`,
                                    )
                                "
                            />
                        </div>
                        <button
                            type="button"
                            tabindex="-1"
                            :aria-label="t('admin.bulkEdit.increase')"
                            class="flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-lg border border-line text-fg-muted hover:border-line-strong hover:text-fg"
                            @click="step(variant, 1)"
                        >
                            <span aria-hidden="true" class="text-base leading-none">+</span>
                        </button>
                    </div>
                    <span v-else class="flex h-10 items-center gap-2 text-sm text-fg-muted">
                        <span class="size-2 rounded-full bg-line-strong" aria-hidden="true" />
                        {{ t('admin.bulkEdit.notTracked') }}
                    </span>
                    <p v-if="errorOf(variant, 'stock_quantity')" class="mt-1 text-xs text-danger">
                        {{ errorOf(variant, 'stock_quantity') }}
                    </p>
                    <p v-else-if="previous(variant, 'stock_quantity')" class="mt-1 text-xs text-fg-muted line-through">
                        {{ previous(variant, 'stock_quantity') }}
                    </p>
                    <p
                        v-else-if="
                            product.track_stock && stockLevel(product, numberOf(variant, 'stock_quantity')) !== 'ok'
                        "
                        :class="[
                            'mt-1 text-xs font-medium',
                            stockLevel(product, numberOf(variant, 'stock_quantity')) === 'out'
                                ? 'text-danger'
                                : 'text-warning',
                        ]"
                    >
                        {{
                            t(`admin.bulkEdit.stockLevels.${stockLevel(product, numberOf(variant, 'stock_quantity'))}`)
                        }}
                    </p>
                </div>

                <!-- Active -->
                <div>
                    <span class="mb-1 block text-xs text-fg-muted xl:sr-only">{{
                        t('admin.bulkEdit.columns.active')
                    }}</span>
                    <div class="flex h-10 items-center">
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="enabled(variant)"
                            :aria-label="label(variant, 'active')"
                            :class="[
                                'inline-flex h-6 w-11 cursor-pointer items-center rounded-full p-0.5 transition-colors focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface focus-visible:outline-none motion-reduce:transition-none',
                                enabled(variant) ? 'bg-primary' : 'bg-line-strong',
                                isEdited(edits, variant.id, 'is_enabled') &&
                                    'ring-2 ring-warning ring-offset-2 ring-offset-surface',
                            ]"
                            @click="emit('change', variant, 'is_enabled', !enabled(variant))"
                        >
                            <span
                                :class="[
                                    'size-5 rounded-full bg-surface shadow transition-transform motion-reduce:transition-none',
                                    enabled(variant) && 'translate-x-5 rtl:-translate-x-5',
                                ]"
                            />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </article>
</template>
