<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import type { Paginated } from '@/shared/types/api';
import AppBadge from '@/shared/ui/AppBadge.vue';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppPagination from '@/shared/ui/AppPagination.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SearchInput from '@/shared/ui/SearchInput.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import SelectField from '@/shared/ui/SelectField.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { brandsApi } from '../../brands/api';
import type { Brand } from '../../brands/types';
import { categoriesApi } from '../../categories/api';
import { flatten } from '../../categories/tree';
import type { CategoryNode } from '../../categories/types';
import { ratingOf } from '../../seo/analysis';
import SeoScoreDot from '../../seo/components/SeoScoreDot.vue';
import { useMoney } from '../../settings/store';
import { productsApi } from '../api';
import type { ProductFilters, ProductListItem, ProductStatus } from '../types';

type StatusFilter = 'all' | ProductStatus;

const { t } = useI18n();
const { formatNumber, formatRelativeTime } = useLocale();
const { format: formatMoney } = useMoney();
const toast = useToast();
const route = useRoute();
const router = useRouter();
const auth = useAdminAuthStore();
const canManage = computed(() => auth.can(Permission.ManageProducts));

const result = ref<Paginated<ProductListItem> | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const tree = ref<CategoryNode[]>([]);
const brands = ref<Brand[]>([]);

// Filters live in the URL so a filtered list can be bookmarked and survives back/forward.
const queryString = (key: string): string => (typeof route.query[key] === 'string' ? route.query[key] : '');

function updateQuery(changes: Record<string, string | undefined>, keepPage = false): void {
    void router.replace({ query: { ...route.query, ...changes, ...(keepPage ? {} : { page: undefined }) } });
}

function queryFilter<T extends string>(key: string, allowed: readonly T[], fallback: T) {
    return computed<T>({
        get: () => (allowed.includes(queryString(key) as T) ? (queryString(key) as T) : fallback),
        set: (value) => updateQuery({ [key]: value === fallback ? undefined : value }),
    });
}

const search = computed({ get: () => queryString('q'), set: (q: string) => updateQuery({ q: q || undefined }) });
const status = queryFilter<StatusFilter>('status', ['all', 'draft', 'published', 'archived'], 'all');
const stock = queryFilter<'all' | 'low' | 'out'>('stock', ['all', 'low', 'out'], 'all');
const category = computed({
    get: () => queryString('category') || 'all',
    set: (value: string) => updateQuery({ category: value === 'all' ? undefined : value }),
});
const brand = computed({
    get: () => queryString('brand') || 'all',
    set: (value: string) => updateQuery({ brand: value === 'all' ? undefined : value }),
});
const needsSeo = computed({
    get: () => queryString('needs_seo') === '1',
    set: (on: boolean) => updateQuery({ needs_seo: on ? '1' : undefined }),
});
const trashed = computed({
    get: () => queryString('trashed') === '1',
    set: (on: boolean) => updateQuery({ trashed: on ? '1' : undefined }),
});
/** Set by the Tags page ("show products"); shown as a removable chip. */
const tagId = computed(() => Number(queryString('tag')) || null);
const page = computed(() => Math.max(1, Number(queryString('page')) || 1));

const statusOptions = computed(() => [
    { value: 'all' as const, label: t('admin.products.all') },
    { value: 'draft' as const, label: t('admin.products.status.draft') },
    { value: 'published' as const, label: t('admin.products.status.published') },
    { value: 'archived' as const, label: t('admin.products.status.archived') },
]);
const stockOptions = computed(() => [
    { value: 'all' as const, label: t('admin.products.all') },
    { value: 'low' as const, label: t('admin.products.stock.low') },
    { value: 'out' as const, label: t('admin.products.stock.out') },
]);
const categoryOptions = computed(() => [
    { value: 'all', label: t('admin.products.all') },
    ...flatten(tree.value).map((item) => ({
        value: String(item.node.id),
        label: `${'— '.repeat(item.depth)}${item.node.name}`,
    })),
]);
const brandOptions = computed(() => [
    { value: 'all', label: t('admin.products.all') },
    ...brands.value.map((item) => ({ value: String(item.id), label: item.name })),
]);

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    const filters: ProductFilters = {
        q: search.value || undefined,
        status: status.value === 'all' ? undefined : status.value,
        stock: stock.value === 'all' ? undefined : stock.value,
        category_id: category.value === 'all' ? undefined : Number(category.value),
        brand_id: brand.value === 'all' ? undefined : Number(brand.value),
        tag_id: tagId.value ?? undefined,
        needs_seo: needsSeo.value ? 1 : undefined,
        trashed: trashed.value ? 1 : undefined,
        page: page.value,
    };

    try {
        result.value = await productsApi.list(filters);
        selected.value = new Set();
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

watch(() => route.query, load, { immediate: true });

void Promise.all([categoriesApi.tree(), brandsApi.list()])
    .then(([loadedTree, loadedBrands]) => {
        tree.value = loadedTree;
        brands.value = loadedBrands;
    })
    .catch(() => undefined);

// ---- selection and bulk status ----

const selected = ref<Set<number>>(new Set());
const allSelected = computed(
    () =>
        !!result.value &&
        result.value.data.length > 0 &&
        result.value.data.every((item) => selected.value.has(item.id)),
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

function toggleAll(): void {
    selected.value = allSelected.value ? new Set() : new Set(result.value?.data.map((item) => item.id) ?? []);
}

const bulkBusy = ref(false);

async function bulk(next: ProductStatus): Promise<void> {
    bulkBusy.value = true;

    try {
        const outcome = await productsApi.changeStatus([...selected.value], next);
        toast.show(t('admin.products.bulkDone', { n: formatNumber(outcome.updated.length) }));

        if (outcome.skipped.length > 0) {
            toast.show(t('admin.products.bulkSkipped', { n: formatNumber(outcome.skipped.length) }), 'danger');
        }
        await load();
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    } finally {
        bulkBusy.value = false;
    }
}

async function restore(item: ProductListItem): Promise<void> {
    try {
        await productsApi.restore(item.id);
        toast.show(t('admin.products.restored', { name: item.name }));
        await load();
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    }
}

// ---- display ----

function priceText(item: ProductListItem): string {
    if (!item.price) {
        return '—';
    }

    return item.price.min === item.price.max
        ? formatMoney(item.price.min)
        : t('admin.products.priceRange', { min: formatMoney(item.price.min), max: formatMoney(item.price.max) });
}

function statusTone(item: ProductListItem): 'success' | 'neutral' | 'warning' {
    if (item.status !== 'published') {
        return 'neutral';
    }

    return item.published_at && new Date(item.published_at) > new Date() ? 'warning' : 'success';
}

function statusText(item: ProductListItem): string {
    return statusTone(item) === 'warning' ? t('admin.products.scheduled') : t(`admin.products.status.${item.status}`);
}

function seoLabel(score: number | null): string {
    const rating = ratingOf(score);

    return rating === null
        ? t('admin.seo.notAnalysed')
        : t('admin.seo.scoreLabel', { score: formatNumber(score ?? 0), rating: t(`admin.seo.rating.${rating}`) });
}

const toggleClass = (on: boolean): string[] => [
    'flex h-11 cursor-pointer items-center gap-2 rounded-[10px] border px-4 text-sm font-medium focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
    on ? 'border-primary bg-primary-soft text-fg' : 'border-line-strong bg-surface text-fg-muted hover:text-fg',
];
</script>

<template>
    <div class="mx-auto flex max-w-7xl flex-col gap-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold">{{ t('admin.products.title') }}</h1>
                <p class="text-sm text-fg-muted">
                    {{ t('admin.products.count', { n: formatNumber(result?.meta.total ?? 0) }) }}
                </p>
            </div>
            <div v-if="canManage" class="flex flex-wrap gap-2">
                <AppButton variant="secondary" size="md" @click="router.push({ name: 'products-bulk' })">
                    <AppIcon name="pen" :size="18" />
                    {{ t('admin.products.bulkEdit') }}
                </AppButton>
                <AppButton size="md" @click="router.push({ name: 'product-create' })">
                    <AppIcon name="plus" :size="18" />
                    {{ t('admin.products.new') }}
                </AppButton>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <SearchInput v-model="search" :label="t('admin.products.search')" />
            <div class="w-full sm:w-auto sm:min-w-96">
                <SegmentedControl v-model="status" :label="t('admin.products.statusLabel')" :options="statusOptions" />
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <SelectField v-model="category" :label="t('admin.products.category')" :options="categoryOptions" />
            <SelectField v-model="brand" :label="t('admin.products.brand')" :options="brandOptions" />
            <SelectField v-model="stock" :label="t('admin.products.stock.title')" :options="stockOptions" />
            <button type="button" :aria-pressed="needsSeo" :class="toggleClass(needsSeo)" @click="needsSeo = !needsSeo">
                <span class="size-2.5 rounded-full bg-warning" aria-hidden="true" />
                {{ t('admin.seo.needsWork') }}
            </button>
            <button
                v-if="tagId"
                type="button"
                :class="toggleClass(true)"
                :aria-label="t('admin.products.clearTagFilter')"
                @click="updateQuery({ tag: undefined })"
            >
                {{ t('admin.products.tagFilter') }}
                <AppIcon name="close" :size="14" />
            </button>
            <button type="button" :aria-pressed="trashed" :class="toggleClass(trashed)" @click="trashed = !trashed">
                <AppIcon name="trash" :size="16" />
                {{ t('admin.products.showDeleted') }}
            </button>
        </div>

        <div
            v-if="canManage && selected.size > 0 && !trashed"
            role="region"
            :aria-label="t('admin.products.bulkLabel')"
            class="flex flex-wrap items-center gap-2 rounded-xl border border-primary/40 bg-primary-soft/50 px-4 py-2.5"
        >
            <span class="text-sm font-semibold">{{
                t('admin.products.selected', { n: formatNumber(selected.size) })
            }}</span>
            <span class="grow" />
            <AppButton variant="secondary" size="md" :loading="bulkBusy" @click="bulk('published')">{{
                t('admin.products.bulkPublish')
            }}</AppButton>
            <AppButton variant="secondary" size="md" :disabled="bulkBusy" @click="bulk('draft')">{{
                t('admin.products.bulkDraft')
            }}</AppButton>
            <AppButton variant="secondary" size="md" :disabled="bulkBusy" @click="bulk('archived')">{{
                t('admin.products.bulkArchive')
            }}</AppButton>
        </div>

        <div v-if="error" class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="grow"><FormAlert :message="error" /></div>
            <AppButton variant="secondary" size="md" :loading="loading" @click="load">{{
                t('common.retry')
            }}</AppButton>
        </div>

        <section
            v-else
            :aria-busy="loading"
            :class="['rounded-2xl border border-line bg-surface transition-opacity', loading && result && 'opacity-60']"
        >
            <div v-if="!result" aria-hidden="true" class="flex flex-col gap-px overflow-hidden rounded-2xl">
                <div
                    v-for="row in 6"
                    :key="row"
                    class="h-18 animate-pulse bg-surface-muted/60 motion-reduce:animate-none"
                />
            </div>

            <EmptyState
                v-else-if="result.data.length === 0"
                :icon="search ? 'search' : 'package'"
                :title="
                    search || route.query.status || route.query.category
                        ? t('admin.products.noResults')
                        : t('admin.products.empty')
                "
                :description="search ? undefined : t('admin.products.emptyText')"
            />

            <template v-else>
                <table class="hidden w-full xl:table">
                    <thead class="bg-bg text-xs font-semibold text-fg-muted">
                        <tr>
                            <th v-if="canManage && !trashed" scope="col" class="w-12 px-4 py-3">
                                <input
                                    type="checkbox"
                                    :checked="allSelected"
                                    :aria-label="t('admin.products.selectAll')"
                                    class="size-4 cursor-pointer accent-[var(--color-primary)]"
                                    @change="toggleAll"
                                />
                            </th>
                            <th scope="col" class="w-full px-4 py-3 text-start">
                                {{ t('admin.products.colProduct') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-start">{{ t('admin.products.colPrice') }}</th>
                            <th scope="col" class="px-4 py-3 text-start">{{ t('admin.products.colStock') }}</th>
                            <th scope="col" class="px-4 py-3 text-start">{{ t('admin.products.colStatus') }}</th>
                            <th scope="col" class="px-4 py-3 text-start">{{ t('admin.products.colUpdated') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="item in result.data" :key="item.id" class="hover:bg-surface-muted/40">
                            <td v-if="canManage && !trashed" class="px-4 py-3">
                                <input
                                    type="checkbox"
                                    :checked="selected.has(item.id)"
                                    :aria-label="t('admin.products.select', { name: item.name })"
                                    class="size-4 cursor-pointer accent-[var(--color-primary)]"
                                    @change="toggle(item.id)"
                                />
                            </td>
                            <!-- max-w-0: the product column takes the remaining width, so long names wrap instead of pushing the other columns out. -->
                            <td class="max-w-0 px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-surface-muted text-fg-muted"
                                    >
                                        <img
                                            v-if="item.image"
                                            :src="item.image"
                                            alt=""
                                            class="size-full object-contain"
                                            loading="lazy"
                                        />
                                        <AppIcon v-else name="package" :size="20" />
                                    </span>
                                    <span class="flex min-w-0 flex-col gap-0.5">
                                        <span class="flex items-center gap-2">
                                            <SeoScoreDot
                                                :rating="ratingOf(item.seo_score)"
                                                :label="seoLabel(item.seo_score)"
                                                size="sm"
                                                class="shrink-0"
                                            />
                                            <RouterLink
                                                :to="{ name: 'product', params: { id: item.id } }"
                                                :title="item.name"
                                                class="line-clamp-2 rounded font-semibold break-words text-fg hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                            >
                                                {{ item.name }}
                                            </RouterLink>
                                        </span>
                                        <span class="truncate text-xs text-fg-muted">
                                            {{
                                                [item.primary_category?.name, item.brand?.name]
                                                    .filter(Boolean)
                                                    .join(' · ') || '—'
                                            }}
                                            <template v-if="item.type === 'variable'">
                                                ·
                                                {{
                                                    t('admin.products.variantsCount', {
                                                        n: formatNumber(item.variants_count),
                                                    })
                                                }}
                                            </template>
                                        </span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap">
                                {{ priceText(item) }}
                                <AppBadge v-if="item.on_sale" tone="warning" class="ms-1">{{
                                    t('admin.products.onSale')
                                }}</AppBadge>
                            </td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap">
                                <span v-if="!item.stock" class="text-fg-muted">{{
                                    t('admin.products.stock.untracked')
                                }}</span>
                                <AppBadge v-else-if="item.stock.out" tone="danger">{{
                                    t('admin.products.stock.out')
                                }}</AppBadge>
                                <span v-else :class="item.stock.low && 'font-semibold text-warning'">
                                    {{ formatNumber(item.stock.total) }}
                                    <span v-if="item.stock.low" class="text-xs"
                                        >({{ t('admin.products.stock.low') }})</span
                                    >
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <AppBadge v-if="item.deleted_at" tone="danger">{{
                                    t('admin.products.deletedBadge')
                                }}</AppBadge>
                                <AppBadge v-else :tone="statusTone(item)">{{ statusText(item) }}</AppBadge>
                            </td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap text-fg-muted">
                                <AppButton
                                    v-if="item.deleted_at && canManage"
                                    variant="secondary"
                                    size="md"
                                    @click="restore(item)"
                                >
                                    {{ t('admin.products.restore') }}
                                </AppButton>
                                <template v-else>{{
                                    item.updated_at ? formatRelativeTime(item.updated_at) : '—'
                                }}</template>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <ul class="divide-y divide-line xl:hidden">
                    <li v-for="item in result.data" :key="item.id" class="flex items-center gap-3 px-4 py-3.5">
                        <input
                            v-if="canManage && !trashed"
                            type="checkbox"
                            :checked="selected.has(item.id)"
                            :aria-label="t('admin.products.select', { name: item.name })"
                            class="size-4 shrink-0 cursor-pointer accent-[var(--color-primary)]"
                            @change="toggle(item.id)"
                        />
                        <span
                            class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-surface-muted text-fg-muted"
                        >
                            <img
                                v-if="item.image"
                                :src="item.image"
                                alt=""
                                class="size-full object-contain"
                                loading="lazy"
                            />
                            <AppIcon v-else name="package" :size="20" />
                        </span>
                        <span class="flex min-w-0 grow flex-col gap-1">
                            <RouterLink
                                :to="{ name: 'product', params: { id: item.id } }"
                                class="truncate font-semibold hover:text-primary"
                            >
                                {{ item.name }}
                            </RouterLink>
                            <span class="text-sm">{{ priceText(item) }}</span>
                            <span class="flex flex-wrap gap-1.5">
                                <AppBadge v-if="item.deleted_at" tone="danger">{{
                                    t('admin.products.deletedBadge')
                                }}</AppBadge>
                                <AppBadge v-else :tone="statusTone(item)">{{ statusText(item) }}</AppBadge>
                                <AppBadge v-if="item.stock?.out" tone="danger">{{
                                    t('admin.products.stock.out')
                                }}</AppBadge>
                                <AppBadge v-else-if="item.stock?.low" tone="warning">{{
                                    t('admin.products.stock.low')
                                }}</AppBadge>
                            </span>
                        </span>
                        <SeoScoreDot :rating="ratingOf(item.seo_score)" :label="seoLabel(item.seo_score)" />
                    </li>
                </ul>

                <AppPagination :meta="result.meta" @change="updateQuery({ page: String($event) }, true)" />
            </template>
        </section>
    </div>
</template>
