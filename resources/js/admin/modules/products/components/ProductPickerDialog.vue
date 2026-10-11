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
import SearchInput from '@/shared/ui/SearchInput.vue';
import SelectField from '@/shared/ui/SelectField.vue';
import { brandsApi } from '../../brands/api';
import { categoriesApi } from '../../categories/api';
import { flatten } from '../../categories/tree';
import { useMoney } from '../../settings/store';
import { productsApi } from '../api';
import type { ProductFilters, ProductListItem, ProductStatus } from '../types';

/** A chosen product. */
export interface PickedProduct {
    id: number;
    name: string;
}

/**
 * The product picker: every product, paginated, with search (name or SKU) and filters (brand,
 * category with its sub-categories, status, availability). The selection is kept across pages and
 * filters; "Select all matching" takes every product the filters match (up to the server's limit).
 * `locked` products are marked and can't be chosen (e.g. already in a sale event); `initial` ones
 * start chosen and can be unticked. The default slot adds fields under the list.
 */
const props = withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        intro?: string;
        /** "add": "Add N products", needs a choice; "choose": "Use N products", an empty choice allowed. */
        mode?: 'add' | 'choose';
        initial?: PickedProduct[];
        locked?: Set<number>;
        lockedLabel?: string;
        /** The parent is working on the choice (e.g. loading variants). */
        busy?: boolean;
        /** An error from the parent's work on the choice. */
        error?: string | null;
    }>(),
    { mode: 'add', initial: () => [], locked: () => new Set<number>(), busy: false, error: null },
);
const emit = defineEmits<{ close: []; confirm: [products: PickedProduct[]] }>();

/** Products per page. */
const PER_PAGE = 20;

const { t } = useI18n();
const { formatNumber } = useLocale();
const money = useMoney();

// ---- filters ----

type Filters = { q: string; brand: string; category: string; status: '' | ProductStatus; stock: '' | 'in' | 'out' };
const EMPTY: Filters = { q: '', brand: '', category: '', status: '', stock: '' };
const filters = ref<Filters>({ ...EMPTY });
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
    sort: 'name',
}));
const filtering = computed(() => Object.values(filters.value).some((value) => value !== ''));

// ---- loading ----

const result = ref<Paginated<ProductListItem> | null>(null);
const loading = ref(false);
const loadError = ref<string | null>(null);
/** Everything the filters match (when within the limit) and how many. */
const matching = ref<{ products: PickedProduct[]; total: number; limit: number } | null>(null);

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
        [result.value, matching.value] = await Promise.all([
            productsApi.list({ ...query.value, page: page.value, per_page: PER_PAGE }),
            // What matches only changes with the filters, not the page.
            matching.value === null ? productsApi.matching(query.value) : Promise.resolve(matching.value),
        ]);
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

        filters.value = { ...EMPTY };
        page.value = 1;
        matching.value = null;
        selected.value = new Map(props.initial.map((product) => [product.id, product]));
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
        if (!props.open) {
            return;
        }

        page.value = 1;
        matching.value = null;
        void load();
    },
);

function goTo(next: number): void {
    page.value = next;
    void load();
}

// ---- selection (kept across pages and filters) ----

const selected = ref<Map<number, PickedProduct>>(new Map());
const isLocked = (id: number): boolean => props.locked.has(id);
const choosable = computed(() => (result.value?.data ?? []).filter((product) => !isLocked(product.id)));
const pageSelected = computed(
    () => choosable.value.length > 0 && choosable.value.every((product) => selected.value.has(product.id)),
);

function toggle(product: PickedProduct): void {
    const next = new Map(selected.value);

    if (next.has(product.id)) {
        next.delete(product.id);
    } else {
        next.set(product.id, { id: product.id, name: product.name });
    }

    selected.value = next;
}

function togglePage(): void {
    const next = new Map(selected.value);
    const on = !pageSelected.value;
    choosable.value.forEach((product) =>
        on ? next.set(product.id, { id: product.id, name: product.name }) : next.delete(product.id),
    );
    selected.value = next;
}

const matchingChoosable = computed(() => (matching.value?.products ?? []).filter((product) => !isLocked(product.id)));
const allMatchingSelected = computed(
    () =>
        matchingChoosable.value.length > 0 &&
        matchingChoosable.value.every((product) => selected.value.has(product.id)),
);
const tooManyMatching = computed(() => matching.value !== null && matching.value.total > matching.value.limit);

function selectAllMatching(): void {
    const next = new Map(selected.value);
    matchingChoosable.value.forEach((product) => next.set(product.id, product));
    selected.value = next;
}

// ---- display ----

function summary(product: ProductListItem): string {
    const price = product.price
        ? product.price.min === product.price.max
            ? money.format(product.price.min)
            : `${money.format(product.price.min)} – ${money.format(product.price.max)}`
        : '—';

    return product.type === 'simple'
        ? t('admin.products.picker.simple', { price })
        : t('admin.products.picker.variable', { n: formatNumber(product.variants_count), price });
}

const statusTone = (status: ProductStatus): 'success' | 'neutral' => (status === 'published' ? 'success' : 'neutral');
const message = computed(() => props.error ?? loadError.value);
</script>

<template>
    <AppDialog
        :open="open"
        :title="title ?? t('admin.products.picker.title')"
        icon="package"
        size="lg"
        :busy="busy"
        @close="emit('close')"
    >
        <div class="flex flex-col gap-4">
            <p class="text-sm leading-relaxed text-fg-muted">{{ intro ?? t('admin.products.picker.intro') }}</p>

            <div class="flex flex-col gap-2.5">
                <SearchInput v-model="filters.q" :label="t('admin.products.picker.search')" />
                <div class="grid gap-2 sm:grid-cols-2">
                    <SelectField
                        v-model="filters.brand"
                        :label="t('admin.products.picker.brand')"
                        :options="brandOptions"
                    />
                    <SelectField
                        v-model="filters.category"
                        :label="t('admin.products.picker.category')"
                        :options="categoryOptions"
                    />
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
                </div>
            </div>

            <FormAlert v-if="message" :message="message" />

            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-[13px]">
                <label v-if="choosable.length > 0" class="flex cursor-pointer items-center gap-2 font-semibold">
                    <input
                        type="checkbox"
                        class="size-4 accent-[var(--color-primary)]"
                        :checked="pageSelected"
                        @change="togglePage"
                    />
                    {{ t('admin.products.picker.selectPage') }}
                </label>
                <template v-if="matching && matching.total > 0">
                    <span v-if="tooManyMatching" class="text-fg-muted">{{
                        t('admin.products.picker.tooMany', {
                            n: formatNumber(matching.total),
                            limit: formatNumber(matching.limit),
                        })
                    }}</span>
                    <span v-else-if="allMatchingSelected" class="text-fg-muted">{{
                        t('admin.products.picker.allMatchingSelected', { n: formatNumber(matchingChoosable.length) })
                    }}</span>
                    <button
                        v-else-if="matchingChoosable.length > 0"
                        type="button"
                        class="rounded font-semibold text-primary hover:underline focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                        @click="selectAllMatching"
                    >
                        {{ t('admin.products.picker.selectMatching', { n: formatNumber(matchingChoosable.length) }) }}
                    </button>
                </template>
                <span class="grow" />
                <button
                    v-if="filtering"
                    type="button"
                    class="rounded text-fg-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    @click="filters = { ...EMPTY }"
                >
                    {{ t('admin.products.picker.clearFilters') }}
                </button>
            </div>

            <div class="rounded-xl border border-line">
                <div v-if="!result" class="flex justify-center py-10"><AppSpinner /></div>
                <p v-else-if="result.data.length === 0" class="px-4 py-8 text-center text-sm text-fg-muted">
                    {{ t('admin.products.picker.noMatches') }}
                </p>
                <ul v-else :aria-busy="loading" :class="['divide-y divide-line', loading && 'opacity-60']">
                    <li v-for="product in result.data" :key="product.id">
                        <label
                            :class="[
                                'flex items-center gap-3 px-3.5 py-2.5',
                                isLocked(product.id)
                                    ? 'cursor-not-allowed bg-surface-muted/40'
                                    : 'cursor-pointer hover:bg-surface-muted/50',
                                selected.has(product.id) && 'bg-primary-soft/40',
                            ]"
                        >
                            <input
                                type="checkbox"
                                class="size-4 shrink-0 accent-[var(--color-primary)]"
                                :checked="selected.has(product.id) || isLocked(product.id)"
                                :disabled="isLocked(product.id)"
                                @change="toggle(product)"
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
                                        · {{ t('admin.products.picker.outOfStock') }}</template
                                    >
                                </span>
                            </span>
                            <AppBadge v-if="isLocked(product.id)" tone="warning" class="shrink-0">{{
                                lockedLabel ?? t('admin.products.picker.already')
                            }}</AppBadge>
                            <AppBadge v-else :tone="statusTone(product.status)" class="shrink-0">{{
                                t(`admin.products.status.${product.status}`)
                            }}</AppBadge>
                        </label>
                    </li>
                </ul>
                <AppPagination v-if="result && result.meta.last_page > 1" :meta="result.meta" @change="goTo" />
            </div>

            <slot />
        </div>

        <template #footer>
            <AppButton
                size="md"
                :disabled="mode === 'add' && selected.size === 0"
                :loading="busy"
                @click="emit('confirm', [...selected.values()])"
                >{{
                    t(mode === 'add' ? 'admin.products.picker.add' : 'admin.products.picker.choose', {
                        n: formatNumber(selected.size),
                    })
                }}</AppButton
            >
            <AppButton
                v-if="selected.size > 0"
                variant="secondary"
                size="md"
                :disabled="busy"
                @click="selected = new Map()"
                >{{ t('admin.products.picker.clear') }}</AppButton
            >
            <AppButton variant="secondary" size="md" :disabled="busy" @click="emit('close')">{{
                t('common.cancel')
            }}</AppButton>
        </template>
    </AppDialog>
</template>
