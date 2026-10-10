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
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import SelectField from '@/shared/ui/SelectField.vue';
import { brandsApi } from '../../brands/api';
import { categoriesApi } from '../../categories/api';
import { flatten } from '../../categories/tree';
import { productsApi } from '../../products/api';
import type { ProductFilters, ProductListItem, ProductStatus } from '../../products/types';
import { useMoney } from '../../settings/store';
import { saleEventsApi } from '../api';
import { type EventRow, rowsFromVariants } from '../saleEvents';

/**
 * The product picker: every product, paginated, with search (name or SKU) and filters (brand,
 * category with its sub-categories, status, availability). The selection is kept across pages and
 * filters; "Select all matching" takes every product the filters match. Each chosen product is
 * added with all its enabled variants. Products already in the event are marked and can't be chosen.
 */
const props = defineProps<{ open: boolean; productIds: Set<number>; variantIds: Set<number> }>();
const emit = defineEmits<{ close: []; add: [rows: EventRow[]] }>();

/** Products per page of the picker. */
const PER_PAGE = 20;
/** The most product ids the variants endpoint takes at once (MatchingProductIdsAction::LIMIT). */
const CHUNK = 500;

const { t } = useI18n();
const { formatNumber } = useLocale();
const money = useMoney();

// ---- filters ----

const filters = ref({
    q: '',
    brand: '',
    category: '',
    status: '' as '' | ProductStatus,
    stock: '' as '' | 'in' | 'out',
});
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
    { value: 'in' as const, label: t('admin.discounts.events.add.inStock') },
    { value: 'out' as const, label: t('admin.discounts.events.add.outOfStock') },
]);

const query = computed<ProductFilters>(() => ({
    q: filters.value.q || undefined,
    brand_id: filters.value.brand ? Number(filters.value.brand) : undefined,
    category_id: filters.value.category ? Number(filters.value.category) : undefined,
    status: filters.value.status || undefined,
    stock: filters.value.stock || undefined,
    sort: 'name',
}));
const filtering = computed(() => Object.values(filters.value).some((value) => value !== ''));

function clearFilters(): void {
    filters.value = { q: '', brand: '', category: '', status: '', stock: '' };
}

// ---- loading ----

const result = ref<Paginated<ProductListItem> | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
/** Everything the filters match: their ids (when within the limit) and how many. */
const matching = ref<{ ids: number[]; total: number; limit: number } | null>(null);

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
    error.value = null;

    try {
        [result.value, matching.value] = await Promise.all([
            productsApi.list({ ...query.value, page: page.value, per_page: PER_PAGE }),
            // The ids only change with the filters, not the page.
            page.value === 1 || matching.value === null
                ? productsApi.ids(query.value)
                : Promise.resolve(matching.value),
        ]);
    } catch (caught) {
        error.value = toApiError(caught).message;
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

        clearFilters();
        page.value = 1;
        matching.value = null;
        selected.value = new Set();
        startValue.value = null;
        void load();

        if (brandOptions.value.length === 0) {
            loadChoices().catch((caught) => (error.value = toApiError(caught).message));
        }
    },
);

watch(
    query,
    () => {
        if (!props.open) {
            return;
        }

        page.value = 1;
        matching.value = null;
        void load();
    },
    { deep: true },
);

function goTo(next: number): void {
    page.value = next;
    void load();
}

// ---- selection (kept across pages and filters) ----

const selected = ref<Set<number>>(new Set());
const inEvent = (product: ProductListItem): boolean => props.productIds.has(product.id);
const choosable = computed(() => (result.value?.data ?? []).filter((product) => !inEvent(product)));
const pageSelected = computed(
    () => choosable.value.length > 0 && choosable.value.every((product) => selected.value.has(product.id)),
);

function toggle(id: number): void {
    const next = new Set(selected.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    selected.value = next;
}

function togglePage(): void {
    const next = new Set(selected.value);
    const on = !pageSelected.value;
    choosable.value.forEach((product) => (on ? next.add(product.id) : next.delete(product.id)));
    selected.value = next;
}

/** The matching products not in the event yet. */
const matchingChoosable = computed(() => (matching.value?.ids ?? []).filter((id) => !props.productIds.has(id)));
const allMatchingSelected = computed(
    () => matchingChoosable.value.length > 0 && matchingChoosable.value.every((id) => selected.value.has(id)),
);
const tooManyMatching = computed(() => matching.value !== null && matching.value.total > matching.value.limit);

function selectAllMatching(): void {
    selected.value = new Set([...selected.value, ...matchingChoosable.value]);
}

// ---- display ----

function summary(product: ProductListItem): string {
    const price = product.price
        ? product.price.min === product.price.max
            ? money.format(product.price.min)
            : `${money.format(product.price.min)} – ${money.format(product.price.max)}`
        : '—';

    return product.type === 'simple'
        ? t('admin.discounts.events.add.simple', { price })
        : t('admin.discounts.events.add.variable', { n: formatNumber(product.variants_count), price });
}

const statusTone = (status: ProductStatus): 'success' | 'neutral' => (status === 'published' ? 'success' : 'neutral');

// ---- adding ----

const startField = ref<'percent' | 'off'>('percent');
const startValue = ref<number | null>(null);
const fieldOptions = computed(() => [
    { value: 'percent' as const, label: t('admin.discounts.events.fields.percent') },
    { value: 'off' as const, label: t('admin.discounts.events.fields.off') },
]);
const adding = ref(false);

async function add(): Promise<void> {
    adding.value = true;
    error.value = null;

    try {
        const ids = [...selected.value];
        const chunks = Array.from({ length: Math.ceil(ids.length / CHUNK) }, (_, index) =>
            ids.slice(index * CHUNK, (index + 1) * CHUNK),
        );
        const variants = (await Promise.all(chunks.map((chunk) => saleEventsApi.variants(chunk)))).flat();
        emit('add', rowsFromVariants(variants, props.variantIds, { field: startField.value, value: startValue.value }));
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        adding.value = false;
    }
}
</script>

<template>
    <AppDialog
        :open="open"
        :title="t('admin.discounts.events.add.title')"
        icon="plus"
        size="lg"
        :busy="adding"
        @close="emit('close')"
    >
        <div class="flex flex-col gap-4">
            <p class="text-sm leading-relaxed text-fg-muted">{{ t('admin.discounts.events.add.intro') }}</p>

            <div class="flex flex-col gap-2.5">
                <SearchInput v-model="filters.q" :label="t('admin.discounts.events.add.search')" />
                <div class="grid gap-2 sm:grid-cols-2">
                    <SelectField
                        v-model="filters.brand"
                        :label="t('admin.discounts.events.add.brand')"
                        :options="brandOptions"
                    />
                    <SelectField
                        v-model="filters.category"
                        :label="t('admin.discounts.events.add.category')"
                        :options="categoryOptions"
                    />
                    <SelectField
                        v-model="filters.status"
                        :label="t('admin.discounts.events.add.status')"
                        :options="statusOptions"
                    />
                    <SelectField
                        v-model="filters.stock"
                        :label="t('admin.discounts.events.add.availability')"
                        :options="stockOptions"
                    />
                </div>
            </div>

            <FormAlert v-if="error" :message="error" />

            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-[13px]">
                <label v-if="choosable.length > 0" class="flex cursor-pointer items-center gap-2 font-semibold">
                    <input
                        type="checkbox"
                        class="size-4 accent-[var(--color-primary)]"
                        :checked="pageSelected"
                        @change="togglePage"
                    />
                    {{ t('admin.discounts.events.add.selectPage') }}
                </label>
                <template v-if="matching && matching.total > 0">
                    <span v-if="tooManyMatching" class="text-fg-muted">{{
                        t('admin.discounts.events.add.tooMany', {
                            n: formatNumber(matching.total),
                            limit: formatNumber(matching.limit),
                        })
                    }}</span>
                    <span v-else-if="allMatchingSelected" class="text-fg-muted">{{
                        t('admin.discounts.events.add.allMatchingSelected', {
                            n: formatNumber(matchingChoosable.length),
                        })
                    }}</span>
                    <button
                        v-else-if="matchingChoosable.length > 0"
                        type="button"
                        class="rounded font-semibold text-primary hover:underline focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                        @click="selectAllMatching"
                    >
                        {{
                            t('admin.discounts.events.add.selectMatching', {
                                n: formatNumber(matchingChoosable.length),
                            })
                        }}
                    </button>
                </template>
                <span class="grow" />
                <button
                    v-if="filtering"
                    type="button"
                    class="rounded text-fg-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    @click="clearFilters"
                >
                    {{ t('admin.discounts.events.add.clearFilters') }}
                </button>
            </div>

            <div class="rounded-xl border border-line">
                <div v-if="!result" class="flex justify-center py-10"><AppSpinner /></div>
                <p v-else-if="result.data.length === 0" class="px-4 py-8 text-center text-sm text-fg-muted">
                    {{ t('admin.discounts.picker.noMatches') }}
                </p>
                <ul v-else :aria-busy="loading" :class="['divide-y divide-line', loading && 'opacity-60']">
                    <li v-for="product in result.data" :key="product.id">
                        <label
                            :class="[
                                'flex items-center gap-3 px-3.5 py-2.5',
                                inEvent(product)
                                    ? 'cursor-not-allowed bg-surface-muted/40'
                                    : 'cursor-pointer hover:bg-surface-muted/50',
                                selected.has(product.id) && 'bg-primary-soft/40',
                            ]"
                        >
                            <input
                                type="checkbox"
                                class="size-4 shrink-0 accent-[var(--color-primary)]"
                                :checked="selected.has(product.id) || inEvent(product)"
                                :disabled="inEvent(product)"
                                @change="toggle(product.id)"
                            />
                            <img
                                v-if="product.image"
                                :src="product.image"
                                alt=""
                                class="size-10 shrink-0 rounded-lg object-cover"
                            />
                            <span
                                v-else
                                class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-surface-muted text-fg-muted"
                                ><AppIcon name="image" :size="18"
                            /></span>
                            <span class="flex min-w-0 grow flex-col">
                                <span class="truncate text-sm font-semibold">{{ product.name }}</span>
                                <span class="truncate text-xs text-fg-muted">
                                    {{ summary(product) }}
                                    <template v-if="product.stock?.out">
                                        · {{ t('admin.discounts.events.add.outOfStock') }}</template
                                    >
                                </span>
                            </span>
                            <AppBadge v-if="inEvent(product)" tone="warning" class="shrink-0">{{
                                t('admin.discounts.events.add.already')
                            }}</AppBadge>
                            <AppBadge v-else :tone="statusTone(product.status)" class="shrink-0">{{
                                t(`admin.products.status.${product.status}`)
                            }}</AppBadge>
                        </label>
                    </li>
                </ul>
                <AppPagination v-if="result && result.meta.last_page > 1" :meta="result.meta" @change="goTo" />
            </div>

            <div class="flex flex-col gap-2">
                <span class="text-sm font-semibold"
                    >{{ t('admin.discounts.events.add.start') }}
                    <span class="font-normal text-fg-muted">{{ t('common.optional') }}</span></span
                >
                <div class="flex flex-wrap items-start gap-3">
                    <div class="w-56">
                        <SegmentedControl
                            v-model="startField"
                            :label="t('admin.discounts.events.add.start')"
                            :options="fieldOptions"
                        />
                    </div>
                    <div class="w-44">
                        <NumberField
                            v-model="startValue"
                            :label="t('admin.discounts.events.add.start')"
                            compact
                            :unit="startField === 'percent' ? '%' : money.unit.value"
                            :factor="startField === 'percent' ? 1 : money.rialsPerUnit.value"
                        />
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <AppButton size="md" :disabled="selected.size === 0" :loading="adding" @click="add">{{
                t('admin.discounts.events.add.confirm', { n: formatNumber(selected.size) })
            }}</AppButton>
            <AppButton
                v-if="selected.size > 0"
                variant="secondary"
                size="md"
                :disabled="adding"
                @click="selected = new Set()"
                >{{ t('admin.discounts.events.bulk.clear') }}</AppButton
            >
            <AppButton variant="secondary" size="md" :disabled="adding" @click="emit('close')">{{
                t('common.cancel')
            }}</AppButton>
        </template>
    </AppDialog>
</template>
