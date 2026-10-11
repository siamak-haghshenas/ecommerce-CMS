<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import AppBadge from '@/shared/ui/AppBadge.vue';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppPagination from '@/shared/ui/AppPagination.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import NumberField from '@/shared/ui/NumberField.vue';
import SearchInput from '@/shared/ui/SearchInput.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { fromLocalInput, toLocalInput } from '../../products/variants';
import { words } from '../../seo/analysis';
import { useMoney } from '../../settings/store';
import { promotionsApi } from '../api';
import DiscountTabs from '../components/DiscountTabs.vue';
import PromotionBulkDialog from '../components/PromotionBulkDialog.vue';
import PromotionDatesDialog from '../components/PromotionDatesDialog.vue';
import PromotionVariantsDialog, { type ChosenVariant } from '../components/PromotionVariantsDialog.vue';
import {
    applyBulk,
    type BulkChange,
    isChanged,
    mapErrors,
    newRows,
    type NewRowDefaults,
    pageOf,
    type PromotionProblem,
    type PromotionRow,
    rowFromPromotion,
    rowProblem,
    rowState,
    rowValues,
    toPayload,
} from '../promotions';
import { groupByProduct, typed } from '../saleEvents';
import type { PromotionState } from '../types';

/**
 * Sales › Discounts › Promotions: one row per SKU discount, grouped by product, each with its own
 * off price / percentage, order limit, discounted quantity and dates. Rows are edited in place
 * (and in bulk) and saved together with "Save all".
 */
const { t } = useI18n();
const { formatNumber, formatDateTime } = useLocale();
const money = useMoney();
const toast = useToast();
const route = useRoute();
const router = useRouter();
const auth = useAdminAuthStore();
const canManage = computed(() => auth.can(Permission.ManageDiscounts));

/** Rows per page. */
const PER_PAGE = 50;

// ---- loading ----

const rows = ref<PromotionRow[] | null>(null);
/** Ids of saved rows removed since the last save. */
const deleted = ref<number[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
let nextKey = 0;

function fill(list: Awaited<ReturnType<typeof promotionsApi.list>>): void {
    rows.value = list.map((promotion) => rowFromPromotion(promotion, toLocalInput));
    deleted.value = [];
    serverErrors.value = new Map();
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        fill(await promotionsApi.list());
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

void load();

// ---- filters and pages (in the URL) ----

type Filter = 'all' | PromotionState | 'problems';
const FILTERS: Filter[] = ['all', 'active', 'scheduled', 'sold_out', 'ended', 'problems'];
const queryString = (key: string): string => (typeof route.query[key] === 'string' ? route.query[key] : '');

const search = computed({
    get: () => queryString('q'),
    set: (q: string) => void router.replace({ query: { ...route.query, q: q || undefined, page: undefined } }),
});
const filter = computed<Filter>({
    get: () => (FILTERS.includes(queryString('status') as Filter) ? (queryString('status') as Filter) : 'all'),
    set: (value) =>
        void router.replace({
            query: { ...route.query, status: value === 'all' ? undefined : value, page: undefined },
        }),
});
const page = computed({
    get: () => Math.max(1, Number(queryString('page')) || 1),
    set: (value: number) =>
        void router.replace({ query: { ...route.query, page: value > 1 ? String(value) : undefined } }),
});

/** Now as a `datetime-local` value, refreshed each minute so states move on. */
const now = ref(toLocalInput(new Date().toISOString()));
const clock = window.setInterval(() => (now.value = toLocalInput(new Date().toISOString())), 60_000);
onBeforeUnmount(() => window.clearInterval(clock));

const serverErrors = ref<Map<string, string>>(new Map());
const problemOf = (row: PromotionRow): PromotionProblem | null => rowProblem(row, rows.value ?? []);
const errorText = (row: PromotionRow): string | null => {
    const problem = problemOf(row);

    return problem ? t(`admin.discounts.promotions.problems.${problem}`) : (serverErrors.value.get(row.key) ?? null);
};
const hasProblem = (row: PromotionRow): boolean => errorText(row) !== null;

const matches = (row: PromotionRow, which: Filter): boolean =>
    which === 'all' || (which === 'problems' ? hasProblem(row) : rowState(row, now.value) === which);
const counts = computed(() =>
    Object.fromEntries(FILTERS.map((which) => [which, (rows.value ?? []).filter((row) => matches(row, which)).length])),
);

const matching = computed(() => {
    const terms = words(search.value);

    return (rows.value ?? []).filter((row) => {
        if (!matches(row, filter.value)) {
            return false;
        }

        const text = terms.length === 0 ? '' : words(`${row.productName} ${row.label} ${row.sku}`).join(' ');

        return terms.every((term) => text.includes(term));
    });
});
const paged = computed(() => pageOf(matching.value, page.value, PER_PAGE));
const groups = computed(() => groupByProduct(paged.value.items));

// ---- editing ----

function update(key: string, change: (row: PromotionRow) => PromotionRow): void {
    rows.value = (rows.value ?? []).map((row) => (row.key === key ? change(row) : row));
}

function remove(keys: Set<string>): void {
    const removed = (rows.value ?? []).filter((row) => keys.has(row.key));
    deleted.value = [...deleted.value, ...removed.flatMap((row) => (row.id === null ? [] : [row.id]))];
    rows.value = (rows.value ?? []).filter((row) => !keys.has(row.key));
    selected.value = new Set([...selected.value].filter((key) => !keys.has(key)));
}

const datesFor = ref<PromotionRow | null>(null);

function applyDates(startsAt: string, endsAt: string): void {
    if (datesFor.value) {
        update(datesFor.value.key, (row) => ({ ...row, startsAt, endsAt }));
    }

    datesFor.value = null;
}

// ---- selection ----

const selected = ref<Set<string>>(new Set());
const selectedRows = computed(() => (rows.value ?? []).filter((row) => selected.value.has(row.key)));
const allOn = (keys: string[]): boolean => keys.length > 0 && keys.every((key) => selected.value.has(key));

function setOn(keys: string[], on: boolean): void {
    const next = new Set(selected.value);
    keys.forEach((key) => (on ? next.add(key) : next.delete(key)));
    selected.value = next;
}

const pageKeys = computed(() => paged.value.items.map((row) => row.key));
const matchingKeys = computed(() => matching.value.map((row) => row.key));

const bulkOpen = ref(false);

function applyBulkChange(change: BulkChange): void {
    const result = applyBulk(rows.value ?? [], selected.value, change);
    rows.value = result.rows;
    bulkOpen.value = false;
    toast.show(
        result.skipped.length > 0
            ? t('admin.discounts.promotions.bulk.appliedSkipped', {
                  n: formatNumber(selected.value.size),
                  m: formatNumber(result.skipped.length),
              })
            : t('admin.discounts.promotions.bulk.applied', { n: formatNumber(selected.value.size) }),
    );
}

// ---- adding ----

const addOpen = ref(false);

function add(chosen: ChosenVariant[], defaults: NewRowDefaults): void {
    const added = newRows(chosen, defaults, nextKey);
    nextKey += added.length;
    rows.value = [...added, ...(rows.value ?? [])];
    addOpen.value = false;
    filter.value = 'all';
    toast.show(t('admin.discounts.promotions.added', { n: formatNumber(added.length) }));
}

// ---- saving ----

const changedCount = computed(() => (rows.value ?? []).filter(isChanged).length + deleted.value.length);
const dirty = computed(() => changedCount.value > 0);
const saving = ref(false);

async function save(): Promise<void> {
    if ((rows.value ?? []).some((row) => problemOf(row) !== null)) {
        filter.value = 'problems';
        toast.show(t('admin.discounts.promotions.fixProblems'), 'danger');

        return;
    }

    const sent = (rows.value ?? []).filter(isChanged);
    saving.value = true;

    try {
        fill(
            await promotionsApi.save(
                sent.map((row) => toPayload(row, fromLocalInput)),
                deleted.value,
            ),
        );
        selected.value = new Set();
        toast.show(t('admin.discounts.promotions.saved'));
    } catch (caught) {
        const apiError = toApiError(caught);

        if (Object.keys(apiError.fieldErrors).length > 0) {
            serverErrors.value = mapErrors(sent, apiError.fieldErrors);
            filter.value = 'problems';
        }

        toast.show(apiError.message, 'danger');
    } finally {
        saving.value = false;
    }
}

const undoOpen = ref(false);

async function undo(): Promise<void> {
    undoOpen.value = false;
    selected.value = new Set();
    await load();
}

// ---- leaving with unsaved changes ----

const leaveTarget = ref<null | (() => void)>(null);
let leaveCancel: (() => void) | null = null;

onBeforeRouteLeave(
    () =>
        new Promise<boolean>((resolve) => {
            if (!dirty.value) {
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

// Keep the page in range when rows go away.
watch(
    () => paged.value.meta.current_page,
    (current) => {
        if (current !== page.value) {
            page.value = current;
        }
    },
);

// ---- display ----

const STATE_TONES: Record<PromotionState, 'success' | 'warning' | 'neutral' | 'danger'> = {
    active: 'success',
    scheduled: 'warning',
    sold_out: 'danger',
    ended: 'neutral',
};

function dateText(value: string): string {
    const iso = fromLocalInput(value);

    return iso ? formatDateTime(iso) : t('admin.discounts.promotions.noDate');
}

function soldText(row: PromotionRow): string {
    return row.quantityLimit === null
        ? t('admin.discounts.promotions.soldUnlimited', { sold: formatNumber(row.soldCount) })
        : t('admin.discounts.promotions.sold', {
              sold: formatNumber(row.soldCount),
              total: formatNumber(row.quantityLimit),
          });
}

/** A lower price from the variant's own sale or a sale event wins over this row, or null. */
function note(row: PromotionRow): { text: string; tone: 'info' | 'muted' } | null {
    if (row.isDeleted) {
        return { text: t('admin.discounts.promotions.deleted'), tone: 'muted' };
    }

    const final = rowValues(row, money.rialsPerUnit.value).final;

    if (
        (row.discountSource === 'sale' || row.discountSource === 'sale_event') &&
        rowState(row, now.value) === 'active' &&
        final !== null &&
        row.currentPrice < final
    ) {
        return {
            text: t(`admin.discounts.promotions.lower.${row.discountSource}`, {
                price: money.format(row.currentPrice),
            }),
            tone: 'info',
        };
    }

    return null;
}

function fieldClass(row: PromotionRow, field: 'percent' | 'final'): string | undefined {
    if (row.field !== field && !(field === 'final' && row.field === 'off')) {
        return undefined;
    }

    return errorText(row) ? 'rounded-lg ring-2 ring-danger' : 'rounded-lg ring-2 ring-primary';
}

const chipClass = (on: boolean): string[] => [
    'inline-flex h-9 items-center gap-1.5 rounded-full border px-3.5 text-[13px] font-semibold focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
    on ? 'border-primary bg-primary-soft text-fg' : 'border-line text-fg-muted hover:text-fg',
];
const dateButtonClass =
    'flex h-10 w-full items-center gap-1.5 truncate rounded-lg border border-line-strong bg-surface px-2.5 text-xs text-fg hover:border-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none disabled:opacity-60';
</script>

<template>
    <div class="mx-auto flex max-w-6xl flex-col gap-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold">{{ t('admin.discounts.title') }}</h1>
                <p class="max-w-3xl text-sm text-fg-muted">{{ t('admin.discounts.promotions.intro') }}</p>
            </div>
            <AppButton v-if="canManage" size="md" @click="addOpen = true">
                <AppIcon name="plus" :size="18" />
                {{ t('admin.discounts.promotions.add') }}
            </AppButton>
        </div>

        <DiscountTabs active="promotions" />

        <div v-if="error" class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="grow"><FormAlert :message="error" /></div>
            <AppButton variant="secondary" size="md" :loading="loading" @click="load">{{
                t('common.retry')
            }}</AppButton>
        </div>

        <template v-else-if="rows">
            <div class="flex flex-col gap-3">
                <SearchInput v-model="search" :label="t('admin.discounts.promotions.search')" />
                <div class="flex flex-wrap gap-2" role="group" :aria-label="t('admin.discounts.statusLabel')">
                    <button
                        v-for="which in FILTERS"
                        :key="which"
                        type="button"
                        :class="[
                            ...chipClass(filter === which),
                            which === 'problems' && counts.problems && 'text-danger',
                        ]"
                        :aria-pressed="filter === which"
                        @click="filter = which"
                    >
                        {{ t(`admin.discounts.promotions.filters.${which}`, { n: formatNumber(counts[which] ?? 0) }) }}
                    </button>
                </div>
            </div>

            <div
                v-if="canManage && matchingKeys.length > 0"
                class="flex flex-wrap items-center gap-x-4 gap-y-2 text-[13px]"
            >
                <label class="flex cursor-pointer items-center gap-2 font-semibold">
                    <input
                        type="checkbox"
                        class="size-4 accent-[var(--color-primary)]"
                        :checked="allOn(matchingKeys)"
                        @change="setOn(matchingKeys, !allOn(matchingKeys))"
                    />
                    {{
                        t('admin.discounts.promotions.selectAll', {
                            products: formatNumber(new Set(matching.map((row) => row.productId)).size),
                            rows: formatNumber(matchingKeys.length),
                        })
                    }}
                </label>
                <label
                    v-if="matchingKeys.length > pageKeys.length"
                    class="flex cursor-pointer items-center gap-2 font-semibold"
                >
                    <input
                        type="checkbox"
                        class="size-4 accent-[var(--color-primary)]"
                        :checked="allOn(pageKeys)"
                        @change="setOn(pageKeys, !allOn(pageKeys))"
                    />
                    {{ t('admin.products.picker.selectPage') }}
                </label>
            </div>

            <div
                v-if="canManage && selected.size > 0"
                class="sticky top-2 z-20 flex flex-wrap items-center gap-2 rounded-xl border border-primary/40 bg-surface px-3 py-2 shadow-sm"
            >
                <span class="me-2 text-sm font-semibold">{{
                    t('admin.discounts.promotions.selected', {
                        rows: formatNumber(selected.size),
                        products: formatNumber(new Set(selectedRows.map((row) => row.productId)).size),
                    })
                }}</span>
                <AppButton variant="secondary" size="md" @click="bulkOpen = true">
                    <AppIcon name="pen" :size="16" />
                    {{ t('admin.discounts.promotions.bulkEdit') }}
                </AppButton>
                <AppButton variant="danger-outline" size="md" @click="remove(new Set(selected))">{{
                    t('admin.discounts.promotions.removeSelected')
                }}</AppButton>
                <button
                    type="button"
                    class="ms-auto rounded-lg px-2 py-1 text-sm text-fg-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    @click="selected = new Set()"
                >
                    {{ t('admin.discounts.events.bulk.clear') }}
                </button>
            </div>

            <section
                v-if="rows.length === 0"
                class="rounded-2xl border border-line bg-surface"
                :aria-label="t('admin.discounts.tabs.promotions')"
            >
                <EmptyState
                    icon="tag"
                    :title="t('admin.discounts.promotions.empty')"
                    :description="t('admin.discounts.promotions.emptyText')"
                >
                    <AppButton v-if="canManage" size="md" @click="addOpen = true">
                        <AppIcon name="plus" :size="18" />
                        {{ t('admin.discounts.promotions.add') }}
                    </AppButton>
                </EmptyState>
            </section>
            <p v-else-if="groups.length === 0" class="py-6 text-center text-sm text-fg-muted">
                {{ t('admin.discounts.promotions.noMatches') }}
            </p>

            <div v-else class="flex flex-col gap-3">
                <article
                    v-for="group in groups"
                    :key="group.productId"
                    class="overflow-hidden rounded-xl border border-line bg-surface"
                >
                    <header class="flex items-center gap-3 bg-surface-muted/50 px-3.5 py-2.5">
                        <input
                            v-if="canManage"
                            type="checkbox"
                            class="size-4 accent-[var(--color-primary)]"
                            :checked="allOn(group.rows.map((row) => row.key))"
                            :aria-label="
                                t('admin.discounts.promotions.selectProduct', {
                                    name: group.rows[0]?.productName ?? '',
                                })
                            "
                            @change="
                                setOn(
                                    group.rows.map((row) => row.key),
                                    !allOn(group.rows.map((row) => row.key)),
                                )
                            "
                        />
                        <img
                            v-if="group.rows[0]?.productImage"
                            :src="group.rows[0].productImage"
                            alt=""
                            class="size-9 shrink-0 rounded-lg object-cover"
                        />
                        <span
                            v-else
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-surface text-fg-muted"
                            ><AppIcon name="image" :size="16"
                        /></span>
                        <span class="flex min-w-0 grow flex-col">
                            <RouterLink
                                :to="{ name: 'product', params: { id: group.productId } }"
                                class="truncate text-sm font-semibold hover:text-primary"
                                >{{ group.rows[0]?.productName }}</RouterLink
                            >
                            <span class="text-xs text-fg-muted">{{
                                group.rows[0]?.productType === 'simple'
                                    ? t('admin.discounts.promotions.simple')
                                    : t('admin.discounts.promotions.rowsInGroup', {
                                          n: formatNumber(group.rows.length),
                                      })
                            }}</span>
                        </span>
                    </header>

                    <ul class="divide-y divide-line">
                        <li
                            v-for="row in group.rows"
                            :key="row.key"
                            :class="[
                                'flex flex-col gap-3 px-3.5 py-3',
                                selected.has(row.key) && 'bg-primary-soft/40',
                                isChanged(row) && 'border-s-4 border-s-warning',
                            ]"
                        >
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                                <input
                                    v-if="canManage"
                                    type="checkbox"
                                    class="size-4 shrink-0 accent-[var(--color-primary)]"
                                    :checked="selected.has(row.key)"
                                    :aria-label="t('admin.discounts.promotions.select', { name: row.label || row.sku })"
                                    @change="setOn([row.key], !selected.has(row.key))"
                                />
                                <span class="flex min-w-0 grow flex-col">
                                    <span class="truncate text-sm font-medium">{{
                                        row.label || t('admin.discounts.promotions.defaultVariant')
                                    }}</span>
                                    <span class="truncate text-xs text-fg-muted" dir="ltr">{{ row.sku }}</span>
                                </span>
                                <span class="text-sm">
                                    <span class="text-xs text-fg-muted"
                                        >{{ t('admin.discounts.promotions.columns.price') }}:
                                    </span>
                                    <span class="font-semibold">{{ money.format(row.price) }}</span>
                                </span>
                                <AppBadge v-if="row.id === null" tone="outline">{{
                                    t('admin.discounts.promotions.new')
                                }}</AppBadge>
                                <AppBadge :tone="STATE_TONES[rowState(row, now)]">{{
                                    t(`admin.discounts.promotions.states.${rowState(row, now)}`)
                                }}</AppBadge>
                                <button
                                    v-if="canManage"
                                    type="button"
                                    class="flex size-10 items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-danger focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                    :aria-label="t('admin.discounts.promotions.remove', { name: row.label || row.sku })"
                                    @click="remove(new Set([row.key]))"
                                >
                                    <AppIcon name="close" :size="18" />
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-6 lg:ps-7">
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs text-fg-muted" aria-hidden="true">{{
                                        t('admin.discounts.promotions.columns.final')
                                    }}</span>
                                    <div :class="fieldClass(row, 'final')">
                                        <NumberField
                                            :model-value="rowValues(row, money.rialsPerUnit.value).final"
                                            :label="t('admin.discounts.promotions.columns.final')"
                                            compact
                                            :unit="money.unit.value"
                                            :factor="money.rialsPerUnit.value"
                                            :disabled="!canManage"
                                            @update:model-value="
                                                update(row.key, (current) => typed(current, 'final', $event))
                                            "
                                        />
                                    </div>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs text-fg-muted" aria-hidden="true">{{
                                        t('admin.discounts.promotions.columns.percent')
                                    }}</span>
                                    <div :class="fieldClass(row, 'percent')">
                                        <NumberField
                                            :model-value="rowValues(row, money.rialsPerUnit.value).percent"
                                            :label="t('admin.discounts.promotions.columns.percent')"
                                            compact
                                            unit="%"
                                            :disabled="!canManage"
                                            @update:model-value="
                                                update(row.key, (current) => typed(current, 'percent', $event))
                                            "
                                        />
                                    </div>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs text-fg-muted" aria-hidden="true">{{
                                        t('admin.discounts.promotions.columns.maxPerOrder')
                                    }}</span>
                                    <NumberField
                                        :model-value="row.maxPerOrder"
                                        :label="t('admin.discounts.promotions.columns.maxPerOrder')"
                                        :placeholder="t('admin.discounts.promotions.noLimit')"
                                        compact
                                        :unit="t('admin.discounts.promotions.unit')"
                                        :disabled="!canManage"
                                        @update:model-value="
                                            update(row.key, (current) => ({ ...current, maxPerOrder: $event }))
                                        "
                                    />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs text-fg-muted" aria-hidden="true">{{
                                        t('admin.discounts.promotions.columns.quantity')
                                    }}</span>
                                    <NumberField
                                        :model-value="row.quantityLimit"
                                        :label="t('admin.discounts.promotions.columns.quantity')"
                                        :placeholder="t('admin.discounts.promotions.unlimited')"
                                        compact
                                        :unit="t('admin.discounts.promotions.unit')"
                                        :disabled="!canManage"
                                        @update:model-value="
                                            update(row.key, (current) => ({ ...current, quantityLimit: $event }))
                                        "
                                    />
                                    <span class="text-[11px] text-fg-muted">{{ soldText(row) }}</span>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs text-fg-muted" aria-hidden="true">{{
                                        t('admin.discounts.promotions.columns.startsAt')
                                    }}</span>
                                    <button
                                        type="button"
                                        :class="dateButtonClass"
                                        :disabled="!canManage"
                                        :aria-label="
                                            t('admin.discounts.promotions.dates.title', { name: row.label || row.sku })
                                        "
                                        @click="datesFor = row"
                                    >
                                        <AppIcon name="clock" :size="14" class="shrink-0" />
                                        <span class="truncate">{{ dateText(row.startsAt) }}</span>
                                    </button>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs text-fg-muted" aria-hidden="true">{{
                                        t('admin.discounts.promotions.columns.endsAt')
                                    }}</span>
                                    <button
                                        type="button"
                                        :class="dateButtonClass"
                                        :disabled="!canManage"
                                        :aria-label="
                                            t('admin.discounts.promotions.dates.title', { name: row.label || row.sku })
                                        "
                                        @click="datesFor = row"
                                    >
                                        <AppIcon name="clock" :size="14" class="shrink-0" />
                                        <span class="truncate">{{ dateText(row.endsAt) }}</span>
                                    </button>
                                </div>
                            </div>

                            <p v-if="errorText(row)" class="flex items-start gap-1.5 text-[13px] text-danger lg:ps-7">
                                <AppIcon name="alert" :size="16" class="mt-0.5 shrink-0" />
                                {{ errorText(row) }}
                            </p>
                            <p
                                v-if="note(row)"
                                :class="[
                                    'rounded-lg px-3 py-2 text-[13px] leading-relaxed lg:ms-7',
                                    note(row)!.tone === 'info'
                                        ? 'bg-primary-soft text-fg'
                                        : 'bg-surface-muted text-fg-muted',
                                ]"
                            >
                                {{ note(row)!.text }}
                            </p>
                        </li>
                    </ul>
                </article>

                <AppPagination v-if="paged.meta.last_page > 1" :meta="paged.meta" @change="page = $event" />
                <p class="text-[13px] leading-relaxed text-fg-muted">
                    {{
                        money.rialsPerUnit.value > 1
                            ? t('admin.discounts.events.roundingToman')
                            : t('admin.discounts.events.roundingRial')
                    }}
                </p>
            </div>
        </template>

        <div class="pointer-events-none sticky bottom-4 z-30 flex justify-center">
            <Transition
                enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
                enter-from-class="translate-y-4 opacity-0"
                leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
                leave-to-class="translate-y-4 opacity-0"
            >
                <div
                    v-if="canManage && dirty"
                    role="region"
                    :aria-label="t('admin.discounts.promotions.saveBar')"
                    class="pointer-events-auto flex flex-wrap items-center gap-2 rounded-2xl bg-fg py-2 ps-4 pe-2 text-bg shadow-[0_12px_32px_rgb(0_0_0/0.25)] sm:rounded-full"
                >
                    <span class="flex items-center gap-2 text-sm font-semibold" aria-live="polite">
                        <span class="size-2 rounded-full bg-warning" aria-hidden="true" />
                        {{ t('admin.discounts.promotions.unsaved', { n: formatNumber(changedCount) }) }}
                    </span>
                    <button
                        type="button"
                        :disabled="saving"
                        class="ms-2 flex h-10 cursor-pointer items-center gap-1.5 rounded-full px-4 text-sm font-medium text-bg/80 hover:bg-bg/10 hover:text-bg focus-visible:ring-2 focus-visible:ring-bg focus-visible:outline-none disabled:opacity-50"
                        @click="undoOpen = true"
                    >
                        <AppIcon name="refresh" :size="16" />
                        {{ t('admin.discounts.promotions.undo') }}
                    </button>
                    <button
                        type="button"
                        :disabled="saving"
                        class="flex h-10 cursor-pointer items-center gap-2 rounded-full bg-primary px-5 text-sm font-semibold text-on-primary hover:bg-primary-hover focus-visible:ring-2 focus-visible:ring-bg focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        @click="save"
                    >
                        <span
                            v-if="saving"
                            class="size-4 animate-spin rounded-full border-2 border-on-primary/40 border-t-on-primary motion-reduce:animate-none"
                            aria-hidden="true"
                        />
                        {{ t('admin.discounts.promotions.saveAll') }}
                    </button>
                </div>
            </Transition>
        </div>

        <template v-if="canManage">
            <PromotionVariantsDialog :open="addOpen" @close="addOpen = false" @add="add" />
            <PromotionBulkDialog
                :open="bulkOpen"
                :rows="selectedRows"
                @close="bulkOpen = false"
                @apply="applyBulkChange"
            />
            <PromotionDatesDialog
                :open="datesFor !== null"
                :name="datesFor?.label || datesFor?.sku || ''"
                :starts-at="datesFor?.startsAt ?? ''"
                :ends-at="datesFor?.endsAt ?? ''"
                @close="datesFor = null"
                @apply="applyDates"
            />
        </template>

        <AppDialog
            :open="undoOpen"
            :title="t('admin.discounts.promotions.undoTitle')"
            icon="alert"
            tone="danger"
            @close="undoOpen = false"
        >
            <p class="text-sm leading-loose">{{ t('admin.discounts.promotions.undoText') }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" @click="undo">{{
                    t('admin.discounts.promotions.undo')
                }}</AppButton>
                <AppButton variant="secondary" size="md" @click="undoOpen = false">{{ t('common.cancel') }}</AppButton>
            </template>
        </AppDialog>

        <AppDialog
            :open="leaveTarget !== null"
            :title="t('admin.discounts.leaveTitle')"
            icon="alert"
            tone="danger"
            @close="stay"
        >
            <p class="text-sm leading-loose">{{ t('admin.discounts.leaveText') }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" @click="leave">{{ t('admin.discounts.leaveConfirm') }}</AppButton>
                <AppButton variant="secondary" size="md" @click="stay">{{ t('admin.discounts.leaveStay') }}</AppButton>
            </template>
        </AppDialog>
    </div>
</template>
