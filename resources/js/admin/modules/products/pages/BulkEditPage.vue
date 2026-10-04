<script setup lang="ts">
import { computed, onBeforeUnmount, ref, shallowRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import type { Paginated } from '@/shared/types/api';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppPagination from '@/shared/ui/AppPagination.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SearchInput from '@/shared/ui/SearchInput.vue';
import SelectField from '@/shared/ui/SelectField.vue';
import { brandsApi } from '../../brands/api';
import type { Brand } from '../../brands/types';
import { categoriesApi } from '../../categories/api';
import { flatten } from '../../categories/tree';
import type { CategoryNode } from '../../categories/types';
import { productsApi } from '../api';
import {
    countChanges,
    mapErrors,
    problems,
    setValue,
    toPayload,
    type BulkProduct,
    type BulkVariant,
    type EditableField,
    type PageEdits,
    type RowErrors,
} from '../bulkEdit';
import BulkProductCard from '../components/BulkProductCard.vue';
import type { ProductFilters, ProductStatus } from '../types';

type QuickFilter = 'all' | 'out' | 'low' | 'sale' | 'inactive';
const QUICK_FILTERS: QuickFilter[] = ['all', 'out', 'low', 'sale', 'inactive'];

const { t } = useI18n();
const { formatNumber } = useLocale();
const toast = useToast();
const route = useRoute();
const router = useRouter();

// ---- filters (in the URL; changing them asks first when there are unsaved changes) ----

const queryString = (key: string): string => (typeof route.query[key] === 'string' ? route.query[key] : '');

function updateQuery(changes: Record<string, string | undefined>, keepPage = false): void {
    whenSaved(() => {
        void router.replace({ query: { ...route.query, ...changes, ...(keepPage ? {} : { page: undefined }) } });
    });
}

function queryModel(key: string, fallback = 'all') {
    return computed({
        get: () => queryString(key) || fallback,
        set: (value: string) => updateQuery({ [key]: value === fallback ? undefined : value }),
    });
}

const search = queryModel('q', '');
const status = queryModel('status');
const category = queryModel('category');
const brand = queryModel('brand');
const perPage = queryModel('per_page', '50');
const quick = computed<QuickFilter>(() =>
    QUICK_FILTERS.includes(queryString('quick') as QuickFilter) ? (queryString('quick') as QuickFilter) : 'all',
);
const page = computed(() => Math.max(1, Number(queryString('page')) || 1));
const showFilters = ref(['status', 'category', 'brand'].some((key) => queryString(key) !== ''));

const tree = ref<CategoryNode[]>([]);
const brands = ref<Brand[]>([]);
const all = computed(() => ({ value: 'all', label: t('admin.products.all') }));
const statusOptions = computed(() => [
    all.value,
    ...(['draft', 'published', 'archived'] as const).map((value) => ({
        value,
        label: t(`admin.products.status.${value}`),
    })),
]);
const categoryOptions = computed(() => [
    all.value,
    ...flatten(tree.value).map((item) => ({
        value: String(item.node.id),
        label: `${'— '.repeat(item.depth)}${item.node.name}`,
    })),
]);
const brandOptions = computed(() => [
    all.value,
    ...brands.value.map((item) => ({ value: String(item.id), label: item.name })),
]);
const perPageOptions = computed(() =>
    ['20', '50', '100'].map((value) => ({
        value,
        label: t('admin.bulkEdit.perPageOption', { n: formatNumber(Number(value)) }),
    })),
);
const activeFilterCount = computed(
    () => [status.value, category.value, brand.value].filter((value) => value !== 'all').length,
);

void Promise.all([categoriesApi.tree(), brandsApi.list()])
    .then(([loadedTree, loadedBrands]) => {
        tree.value = loadedTree;
        brands.value = loadedBrands;
    })
    .catch(() => undefined);

// ---- loading ----

const products = ref<BulkProduct[]>([]);
const meta = ref<Paginated<BulkProduct>['meta'] | null>(null);
const quickCounts = ref<Record<QuickFilter, number> | null>(null);
const loading = ref(false);
const loaded = ref(false);
const loadError = ref<string | null>(null);

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;

    const filters: ProductFilters = {
        q: search.value || undefined,
        status: status.value === 'all' ? undefined : (status.value as ProductStatus),
        category_id: category.value === 'all' ? undefined : Number(category.value),
        brand_id: brand.value === 'all' ? undefined : Number(brand.value),
        quick: quick.value === 'all' ? undefined : quick.value,
        page: page.value,
        per_page: Number(perPage.value),
    };

    try {
        const result = await productsApi.bulkList(filters);
        products.value = result.data;
        meta.value = result.meta;
        quickCounts.value = result.meta.quick_counts;
        loaded.value = true;
    } catch (caught) {
        loadError.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

watch(() => route.query, load, { immediate: true });

const quickFilters = computed(() =>
    QUICK_FILTERS.map((value) => ({
        value,
        label: t(`admin.bulkEdit.quick.${value}`),
        count: quickCounts.value?.[value],
    })),
);

// ---- changes ----

const edits = shallowRef<PageEdits>({});
const serverErrors = ref<RowErrors>({});
const saved = ref<Set<number>>(new Set());
const changes = computed(() => countChanges(edits.value));
const variants = computed(() => products.value.flatMap((product) => product.variants));
const hasProblems = computed(() =>
    variants.value.some((variant) => Object.keys(problems(edits.value, variant)).length > 0),
);

/** Where each product's first variant sits on the page, for moving down a column with Enter. */
const firstIndexes = computed(() => {
    let index = 0;

    return products.value.map((product) => {
        const first = index;
        index += product.variants.length;

        return first;
    });
});

function onChange(variant: BulkVariant, field: EditableField, value: number | boolean | null): void {
    edits.value = setValue(edits.value, variant, field, value as never);

    if (serverErrors.value[variant.id]?.[field]) {
        const next = { ...serverErrors.value };
        const rest = { ...(next[variant.id] ?? {}) };
        delete rest[field];
        next[variant.id] = rest;
        serverErrors.value = next;
    }
}

/** Enter: the same field in the next (Shift: previous) row. */
function onKeydown(event: KeyboardEvent): void {
    const cell = event.target instanceof HTMLInputElement ? event.target.closest<HTMLElement>('[data-field]') : null;

    if (event.key !== 'Enter' || !cell) {
        return;
    }

    event.preventDefault();
    const index = Number(cell.dataset.index) + (event.shiftKey ? -1 : 1);
    document
        .querySelector<HTMLInputElement>(`[data-field="${cell.dataset.field}"][data-index="${index}"] input`)
        ?.focus();
}

// ---- saving (every change on the page, all or nothing) ----

const saving = ref(false);
const saveError = ref<string | null>(null);

/** Returns whether everything was saved. */
async function save(): Promise<boolean> {
    if (changes.value === 0) {
        return true;
    }

    if (hasProblems.value) {
        saveError.value = t('admin.bulkEdit.fixErrors');

        return false;
    }

    const payload = toPayload(edits.value, products.value);
    saving.value = true;
    saveError.value = null;

    try {
        const savedIds = await productsApi.bulkSave(payload);
        toast.show(t('admin.bulkEdit.saved', { n: formatNumber(payload.length) }));
        edits.value = {};
        serverErrors.value = {};
        await load();
        saved.value = new Set(savedIds);
        setTimeout(() => (saved.value = new Set()), 4000);

        return true;
    } catch (caught) {
        const error = toApiError(caught);
        serverErrors.value = mapErrors(payload, error.fieldErrors);
        saveError.value = Object.keys(serverErrors.value).length > 0 ? t('admin.bulkEdit.saveFailed') : error.message;

        // Stock that changed meanwhile: reload the rows (the changes stay) so the next save compares against it.
        if (Object.values(serverErrors.value).some((row) => row.stock_quantity !== undefined)) {
            const errors = serverErrors.value;
            await load();
            serverErrors.value = errors;
        }

        return false;
    } finally {
        saving.value = false;
    }
}

// ---- undo ----

const confirmUndo = ref(false);

function undo(): void {
    confirmUndo.value = false;
    edits.value = {};
    serverErrors.value = {};
    saveError.value = null;
}

// ---- unsaved changes: filters, pages and leaving ask first ----

const pending = ref<null | { proceed: () => void; cancel?: () => void }>(null);

function whenSaved(proceed: () => void, cancel?: () => void): void {
    if (changes.value === 0) {
        proceed();

        return;
    }

    pending.value = { proceed, cancel };
}

async function saveAndContinue(): Promise<void> {
    const next = pending.value;

    if (next && (await save())) {
        pending.value = null;
        next.proceed();
    } else {
        // The errors are shown on the rows: stay to fix them.
        next?.cancel?.();
        pending.value = null;
    }
}

function discardAndContinue(): void {
    const next = pending.value;
    undo();
    pending.value = null;
    next?.proceed();
}

function stay(): void {
    pending.value?.cancel?.();
    pending.value = null;
}

/** Filter and page changes stay on this page (they were already asked about). */
onBeforeRouteLeave(
    () =>
        new Promise<boolean>((resolve) =>
            whenSaved(
                () => resolve(true),
                () => resolve(false),
            ),
        ),
);

const beforeUnload = (event: BeforeUnloadEvent): void => {
    if (changes.value > 0) {
        event.preventDefault();
    }
};
window.addEventListener('beforeunload', beforeUnload);
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
</script>

<template>
    <div class="mx-auto flex max-w-7xl flex-col gap-5 pb-4" @keydown="onKeydown">
        <!-- Title -->
        <div class="flex flex-col gap-1">
            <RouterLink
                :to="{ name: 'products' }"
                class="flex w-fit items-center gap-1 rounded text-sm text-fg-muted hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            >
                <AppIcon name="chevronNext" :size="16" class="rotate-180 rtl:rotate-0" />
                {{ t('admin.products.title') }}
            </RouterLink>
            <h1 class="text-2xl font-bold">{{ t('admin.bulkEdit.title') }}</h1>
            <p class="text-sm text-fg-muted">{{ t('admin.bulkEdit.subtitle') }}</p>
        </div>

        <!-- Search, quick filters, more filters -->
        <div class="flex flex-col gap-3 rounded-2xl border border-line bg-surface p-3">
            <div class="flex flex-wrap items-center gap-2">
                <div class="min-w-64 grow">
                    <SearchInput v-model="search" :label="t('admin.bulkEdit.search')" />
                </div>
                <button
                    type="button"
                    :aria-expanded="showFilters"
                    :class="[
                        'flex h-11 cursor-pointer items-center gap-2 rounded-[10px] border px-4 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                        showFilters || activeFilterCount
                            ? 'border-primary bg-primary-soft text-fg'
                            : 'border-line-strong text-fg-muted hover:text-fg',
                    ]"
                    @click="showFilters = !showFilters"
                >
                    <AppIcon name="sliders" :size="16" />
                    {{ t('admin.bulkEdit.filters') }}
                    <span
                        v-if="activeFilterCount"
                        class="flex size-5 items-center justify-center rounded-full bg-primary text-[11px] text-on-primary"
                        >{{ formatNumber(activeFilterCount) }}</span
                    >
                </button>
            </div>

            <div role="group" :aria-label="t('admin.bulkEdit.quickLabel')" class="flex flex-wrap gap-2">
                <button
                    v-for="item in quickFilters"
                    :key="item.value"
                    type="button"
                    :aria-pressed="quick === item.value"
                    :class="[
                        'flex h-9 cursor-pointer items-center gap-2 rounded-full border px-3.5 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none motion-reduce:transition-none',
                        quick === item.value
                            ? 'border-fg bg-fg font-semibold text-bg'
                            : 'border-line text-fg-muted hover:border-line-strong hover:text-fg',
                    ]"
                    @click="updateQuery({ quick: item.value === 'all' ? undefined : item.value })"
                >
                    {{ item.label }}
                    <span
                        v-if="item.count !== undefined"
                        :class="[
                            'rounded-full px-1.5 text-xs tabular-nums',
                            quick === item.value ? 'bg-bg/20' : 'bg-surface-muted',
                        ]"
                        >{{ formatNumber(item.count) }}</span
                    >
                </button>
            </div>

            <div v-if="showFilters" class="flex flex-wrap items-center gap-3 border-t border-line pt-3">
                <SelectField v-model="status" :label="t('admin.products.statusLabel')" :options="statusOptions" />
                <SelectField v-model="category" :label="t('admin.products.category')" :options="categoryOptions" />
                <SelectField v-model="brand" :label="t('admin.products.brand')" :options="brandOptions" />
                <span class="grow" />
                <SelectField v-model="perPage" :label="t('admin.bulkEdit.perPage')" :options="perPageOptions" />
            </div>
        </div>

        <FormAlert v-if="saveError" :message="saveError" />

        <div v-if="loadError" class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="grow"><FormAlert :message="loadError" /></div>
            <AppButton variant="secondary" size="md" :loading="loading" @click="load">{{
                t('common.retry')
            }}</AppButton>
        </div>

        <!-- First load -->
        <div v-else-if="!loaded" aria-hidden="true" class="flex flex-col gap-3">
            <div
                v-for="row in 4"
                :key="row"
                class="h-40 animate-pulse rounded-2xl bg-surface-muted/60 motion-reduce:animate-none"
            />
        </div>

        <EmptyState
            v-else-if="products.length === 0"
            icon="search"
            :title="t('admin.bulkEdit.empty')"
            class="rounded-2xl border border-line bg-surface"
        />

        <template v-else>
            <!-- Column titles (desktop) -->
            <div
                class="hidden grid-cols-[minmax(0,1fr)_8rem_11rem_12rem_9.5rem_4rem] gap-x-3 px-[calc(1rem+1px)] text-xs font-semibold text-fg-muted xl:grid"
                aria-hidden="true"
            >
                <span>{{ t('admin.bulkEdit.columns.variant') }}</span>
                <span>{{ t('admin.bulkEdit.columns.sku') }}</span>
                <span>{{ t('admin.bulkEdit.columns.price') }}</span>
                <span>{{ t('admin.bulkEdit.columns.sale') }}</span>
                <span>{{ t('admin.bulkEdit.columns.stock') }}</span>
                <span>{{ t('admin.bulkEdit.columns.active') }}</span>
            </div>

            <div :aria-busy="loading" :class="['flex flex-col gap-3 transition-opacity', loading && 'opacity-60']">
                <BulkProductCard
                    v-for="(product, index) in products"
                    :key="product.id"
                    :product="product"
                    :edits="edits"
                    :server-errors="serverErrors"
                    :saved="saved"
                    :first-index="firstIndexes[index] ?? 0"
                    @change="onChange"
                />
            </div>

            <div class="flex flex-col gap-2">
                <AppPagination v-if="meta" :meta="meta" @change="updateQuery({ page: String($event) }, true)" />
                <p class="hidden px-1 text-xs text-fg-muted xl:block">{{ t('admin.bulkEdit.keyboardHint') }}</p>
            </div>
        </template>

        <!-- Floating save bar: appears when there are changes -->
        <div class="pointer-events-none sticky bottom-4 z-30 flex justify-center">
            <Transition
                enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
                enter-from-class="translate-y-4 opacity-0"
                leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
                leave-to-class="translate-y-4 opacity-0"
            >
                <div
                    v-if="changes"
                    role="region"
                    :aria-label="t('admin.bulkEdit.saveBar')"
                    class="pointer-events-auto flex flex-wrap items-center gap-2 rounded-2xl bg-fg py-2 ps-4 pe-2 text-bg shadow-[0_12px_32px_rgb(0_0_0/0.25)] sm:rounded-full"
                >
                    <span class="flex items-center gap-2 text-sm font-semibold" aria-live="polite">
                        <span class="size-2 rounded-full bg-warning" aria-hidden="true" />
                        {{ t('admin.bulkEdit.unsaved', { n: formatNumber(changes) }) }}
                    </span>
                    <button
                        type="button"
                        :disabled="saving"
                        class="ms-2 flex h-10 cursor-pointer items-center gap-1.5 rounded-full px-4 text-sm font-medium text-bg/80 hover:bg-bg/10 hover:text-bg focus-visible:ring-2 focus-visible:ring-bg focus-visible:outline-none disabled:opacity-50"
                        @click="confirmUndo = true"
                    >
                        <AppIcon name="refresh" :size="16" />
                        {{ t('admin.bulkEdit.undo') }}
                    </button>
                    <button
                        type="button"
                        :disabled="saving || hasProblems"
                        class="flex h-10 cursor-pointer items-center gap-2 rounded-full bg-primary px-5 text-sm font-semibold text-on-primary hover:bg-primary-hover focus-visible:ring-2 focus-visible:ring-bg focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        @click="save"
                    >
                        <span
                            v-if="saving"
                            class="size-4 animate-spin rounded-full border-2 border-on-primary/40 border-t-on-primary motion-reduce:animate-none"
                            aria-hidden="true"
                        />
                        {{ t('admin.bulkEdit.save') }}
                    </button>
                </div>
            </Transition>
        </div>

        <AppDialog
            :open="confirmUndo"
            :title="t('admin.bulkEdit.undoTitle')"
            icon="refresh"
            @close="confirmUndo = false"
        >
            <p class="text-sm leading-loose">{{ t('admin.bulkEdit.undoText', { n: formatNumber(changes) }) }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" @click="undo">{{ t('admin.bulkEdit.undoConfirm') }}</AppButton>
                <AppButton variant="secondary" size="md" @click="confirmUndo = false">{{
                    t('common.cancel')
                }}</AppButton>
            </template>
        </AppDialog>

        <AppDialog
            :open="pending !== null"
            :title="t('admin.bulkEdit.leaveTitle')"
            icon="alert"
            :busy="saving"
            @close="stay"
        >
            <p class="text-sm leading-loose">{{ t('admin.bulkEdit.leaveText', { n: formatNumber(changes) }) }}</p>
            <template #footer>
                <AppButton size="md" :loading="saving" :disabled="hasProblems" @click="saveAndContinue">{{
                    t('admin.bulkEdit.saveAndContinue')
                }}</AppButton>
                <AppButton variant="danger-outline" size="md" :disabled="saving" @click="discardAndContinue">{{
                    t('admin.bulkEdit.discard')
                }}</AppButton>
                <AppButton variant="secondary" size="md" :disabled="saving" @click="stay">{{
                    t('admin.bulkEdit.stay')
                }}</AppButton>
            </template>
        </AppDialog>
    </div>
</template>
