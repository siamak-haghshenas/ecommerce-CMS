<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import NumberField from '@/shared/ui/NumberField.vue';
import { fromLocalInput } from '../../products/variants';
import { useMoney } from '../../settings/store';
import { saleEventsApi } from '../api';
import {
    applyDates,
    applyDiscount,
    applyLimit,
    limitInvalid,
    type DiscountField,
    type EventRow,
    groupByProduct,
    linkedValues,
    type RowProblem,
    rowProblem,
    rowsFromVariants,
    typed,
} from '../saleEvents';
import AddProductsDialog from './AddProductsDialog.vue';
import SetDatesDialog from './SetDatesDialog.vue';
import SetDiscountDialog from './SetDiscountDialog.vue';
import SetLimitDialog from './SetLimitDialog.vue';

/**
 * The event's variants, grouped by product. Each row has linked %, amount off and final price
 * fields (the outlined one is what is saved), its own optional dates and notes on what else
 * decides the price. Selected rows get bulk actions.
 */
const props = defineProps<{
    eventId: number | null;
    /** `datetime-local` values of the event's dates. */
    eventStartsAt: string;
    eventEndsAt: string;
    /** Server errors by variant id. */
    errors: Map<number, string>;
    /** An error about the whole list (e.g. too many variants). */
    listError?: string;
    canManage: boolean;
}>();

const rows = defineModel<EventRow[]>({ required: true });

const { t } = useI18n();
const { formatNumber, formatDateTime } = useLocale();
const money = useMoney();
const toast = useToast();

const eventWindow = computed(() => ({ startsAt: props.eventStartsAt, endsAt: props.eventEndsAt }));
const DISCOUNT_PROBLEMS: RowProblem[] = ['required', 'percent_range', 'not_below_price'];

const problemOf = (row: EventRow): RowProblem | null => rowProblem(row, eventWindow.value);
const hasProblem = (row: EventRow): boolean => problemOf(row) !== null || props.errors.has(row.variantId);
const errorText = (row: EventRow): string | null => {
    const problem = problemOf(row);

    return problem ? t(`admin.discounts.events.problems.${problem}`) : (props.errors.get(row.variantId) ?? null);
};

// ---- filters ----

type Filter = 'all' | 'overridden' | 'own' | 'problems';
const filter = ref<Filter>('all');
const matches = (row: EventRow, which: Filter): boolean =>
    which === 'all' ||
    (which === 'overridden' && row.overriddenBy.length > 0) ||
    (which === 'own' && (row.startsAt !== '' || row.endsAt !== '')) ||
    (which === 'problems' && hasProblem(row));
const filterCounts = computed(() => ({
    overridden: rows.value.filter((row) => matches(row, 'overridden')).length,
    own: rows.value.filter((row) => matches(row, 'own')).length,
    problems: rows.value.filter((row) => matches(row, 'problems')).length,
}));
const groups = computed(() =>
    groupByProduct(rows.value)
        .map((group) => ({ ...group, shown: group.rows.filter((row) => matches(row, filter.value)) }))
        .filter((group) => group.shown.length > 0),
);
const productCount = computed(() => new Set(rows.value.map((row) => row.productId)).size);

/** Shows the rows with problems, e.g. when Save finds some. */
function showProblems(): void {
    filter.value = 'problems';
}

defineExpose({ showProblems });

// ---- editing ----

function update(variantId: number, change: (row: EventRow) => EventRow): void {
    rows.value = rows.value.map((row) => (row.variantId === variantId ? change(row) : row));
}

function onType(row: EventRow, field: DiscountField, value: number | null): void {
    update(row.variantId, (current) => typed(current, field, value));
}

function remove(ids: Set<number>): void {
    rows.value = rows.value.filter((row) => !ids.has(row.variantId));
    selected.value = new Set([...selected.value].filter((id) => !ids.has(id)));
}

// ---- selection and bulk actions ----

const selected = ref<Set<number>>(new Set());
const selectedRows = computed(() => rows.value.filter((row) => selected.value.has(row.variantId)));

function toggle(ids: number[], on: boolean): void {
    const next = new Set(selected.value);
    ids.forEach((id) => (on ? next.add(id) : next.delete(id)));
    selected.value = next;
}

const discountOpen = ref(false);
/** The rows the dates dialog applies to (one row's date button, or the selection). */
const datesFor = ref<Set<number> | null>(null);
const addOpen = ref(false);

function applyBulkDiscount(field: DiscountField, value: number): void {
    const result = applyDiscount(rows.value, selected.value, field, value);
    rows.value = result.rows;
    discountOpen.value = false;
    toast.show(
        result.skipped.length > 0
            ? t('admin.discounts.events.bulk.appliedSkipped', { n: formatNumber(result.skipped.length) })
            : t('admin.discounts.events.bulk.applied'),
    );
}

const limitOpen = ref(false);

function applyBulkLimit(limit: number | null): void {
    rows.value = applyLimit(rows.value, selected.value, limit);
    limitOpen.value = false;
    toast.show(t('admin.discounts.limit.applied', { n: formatNumber(selected.value.size) }));
}

function setLimit(row: EventRow, limit: number | null): void {
    rows.value = applyLimit(rows.value, new Set([row.variantId]), limit);
}

function applyBulkDates(startsAt: string, endsAt: string): void {
    if (datesFor.value) {
        rows.value = applyDates(rows.value, datesFor.value, startsAt, endsAt);
    }

    datesFor.value = null;
}

const datesRow = computed(() => {
    const ids = datesFor.value;

    return ids && ids.size === 1 ? rows.value.find((row) => ids.has(row.variantId)) : undefined;
});

// ---- adding ----

const productIds = computed(() => new Set(rows.value.map((row) => row.productId)));
const variantIds = computed(() => new Set(rows.value.map((row) => row.variantId)));

function onAdd(added: EventRow[]): void {
    rows.value = [...rows.value, ...added];
    addOpen.value = false;
    toast.show(t('admin.discounts.events.add.added', { n: formatNumber(added.length) }));
}

/** Adds the product's enabled variants that aren't in the event yet, with the first row's percentage. */
async function addMissingVariants(productId: number): Promise<void> {
    const first = rows.value.find((row) => row.productId === productId);

    try {
        const added = rowsFromVariants(await saleEventsApi.variants([productId]), variantIds.value, {
            field: 'percent',
            value: first?.field === 'percent' ? first.value : null,
        });

        if (added.length === 0) {
            toast.show(t('admin.discounts.events.add.none'));

            return;
        }

        const index = rows.value.map((row) => row.productId).lastIndexOf(productId);
        rows.value = [...rows.value.slice(0, index + 1), ...added, ...rows.value.slice(index + 1)];
        toast.show(t('admin.discounts.events.add.added', { n: formatNumber(added.length) }));
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    }
}

// ---- display ----

const dateText = (value: string): string => formatDateTime(fromLocalInput(value) ?? '');

function datesLabel(row: EventRow): string {
    if (row.startsAt === '' && row.endsAt === '') {
        return t('admin.discounts.events.eventDates');
    }

    return t('admin.discounts.window.between', {
        start: row.startsAt === '' ? '…' : dateText(row.startsAt),
        end: row.endsAt === '' ? '…' : dateText(row.endsAt),
    });
}

/** What else decides the variant's price, or null. */
function note(row: EventRow): { text: string; tone: 'warning' | 'info' | 'muted' } | null {
    if (row.isDeleted) {
        return { text: t('admin.discounts.events.notes.deleted'), tone: 'muted' };
    }

    if (!row.isEnabled) {
        return { text: t('admin.discounts.events.notes.disabled'), tone: 'muted' };
    }

    const over = row.overriddenBy[0];

    if (over) {
        return {
            text: t('admin.discounts.events.notes.overridden', {
                name: over.name,
                start: formatDateTime(over.starts_at),
                end: formatDateTime(over.ends_at),
            }),
            tone: 'warning',
        };
    }

    const final = linkedValues(row, money.rialsPerUnit.value).final;
    const fromOther = row.discountSource === 'sale' || row.discountSource === 'promotion';

    if (
        fromOther &&
        props.eventId !== null &&
        row.saleEventId !== props.eventId &&
        final !== null &&
        row.currentPrice < final
    ) {
        return {
            text: t(`admin.discounts.events.notes.lower.${row.discountSource}`, {
                price: money.format(row.currentPrice),
            }),
            tone: 'info',
        };
    }

    return null;
}

const noteClass = {
    warning: 'bg-warning-soft text-fg',
    info: 'bg-primary-soft text-fg',
    muted: 'bg-surface-muted text-fg-muted',
};

function fieldClass(row: EventRow, field: DiscountField): string | undefined {
    if (row.field !== field) {
        return undefined;
    }

    const problem = problemOf(row);
    const bad = (problem !== null && DISCOUNT_PROBLEMS.includes(problem)) || props.errors.has(row.variantId);

    return bad ? 'rounded-lg ring-2 ring-danger' : 'rounded-lg ring-2 ring-primary';
}

const chipClass = (on: boolean): string[] => [
    'inline-flex h-9 items-center gap-1.5 rounded-full border px-3.5 text-[13px] font-semibold focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
    on ? 'border-primary bg-primary-soft text-fg' : 'border-line text-fg-muted hover:text-fg',
];
const GRID = 'lg:grid-cols-[minmax(0,1fr)_7.5rem_6.5rem_8.5rem_8.5rem_6.5rem_9rem_2.5rem]';
</script>

<template>
    <section
        class="flex flex-col gap-4.5 rounded-2xl border border-line bg-surface p-5 sm:p-6"
        aria-labelledby="event-products-title"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex min-w-0 flex-col gap-1">
                <h2 id="event-products-title" class="text-lg font-bold">
                    {{
                        t('admin.discounts.events.sections.products', {
                            products: formatNumber(productCount),
                            variants: formatNumber(rows.length),
                        })
                    }}
                </h2>
                <p class="max-w-3xl text-[13px] leading-relaxed text-fg-muted">
                    {{ t('admin.discounts.events.productsIntro') }}
                </p>
            </div>
            <AppButton v-if="canManage" variant="secondary" size="md" @click="addOpen = true">
                <AppIcon name="plus" :size="18" />
                {{ t('admin.discounts.events.add.open') }}
            </AppButton>
        </div>

        <p v-if="listError" class="text-[13px] text-danger">{{ listError }}</p>

        <div
            v-if="rows.length > 0"
            class="flex flex-wrap gap-2"
            role="group"
            :aria-label="t('admin.discounts.events.filterLabel')"
        >
            <button
                type="button"
                :class="chipClass(filter === 'all')"
                :aria-pressed="filter === 'all'"
                @click="filter = 'all'"
            >
                {{ t('admin.discounts.all') }}
            </button>
            <button
                type="button"
                :class="chipClass(filter === 'overridden')"
                :aria-pressed="filter === 'overridden'"
                @click="filter = 'overridden'"
            >
                {{ t('admin.discounts.events.filters.overridden', { n: formatNumber(filterCounts.overridden) }) }}
            </button>
            <button
                type="button"
                :class="chipClass(filter === 'own')"
                :aria-pressed="filter === 'own'"
                @click="filter = 'own'"
            >
                {{ t('admin.discounts.events.filters.own', { n: formatNumber(filterCounts.own) }) }}
            </button>
            <button
                type="button"
                :class="[...chipClass(filter === 'problems'), filterCounts.problems > 0 && 'text-danger']"
                :aria-pressed="filter === 'problems'"
                @click="filter = 'problems'"
            >
                {{ t('admin.discounts.events.filters.problems', { n: formatNumber(filterCounts.problems) }) }}
            </button>
        </div>

        <div
            v-if="canManage && selected.size > 0"
            class="sticky top-2 z-20 flex flex-wrap items-center gap-2 rounded-xl border border-primary/40 bg-surface px-3 py-2 shadow-sm"
        >
            <span class="me-2 text-sm font-semibold">{{
                t('admin.discounts.events.bulk.selected', { n: formatNumber(selected.size) })
            }}</span>
            <AppButton variant="secondary" size="md" @click="discountOpen = true">{{
                t('admin.discounts.events.bulk.setDiscount')
            }}</AppButton>
            <AppButton variant="secondary" size="md" @click="limitOpen = true">{{
                t('admin.discounts.limit.bulk')
            }}</AppButton>
            <AppButton variant="secondary" size="md" @click="datesFor = new Set(selected)">{{
                t('admin.discounts.events.bulk.setDates')
            }}</AppButton>
            <AppButton variant="secondary" size="md" @click="rows = applyDates(rows, selected, '', '')">{{
                t('admin.discounts.events.bulk.useEventDates')
            }}</AppButton>
            <AppButton variant="danger-outline" size="md" @click="remove(new Set(selected))">{{
                t('admin.discounts.events.bulk.remove')
            }}</AppButton>
            <button
                type="button"
                class="ms-auto rounded-lg px-2 py-1 text-sm text-fg-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                @click="selected = new Set()"
            >
                {{ t('admin.discounts.events.bulk.clear') }}
            </button>
        </div>

        <div
            v-if="rows.length === 0"
            class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-line-strong px-4 py-10 text-center"
        >
            <AppIcon name="package" :size="28" class="text-fg-muted" />
            <p class="text-sm font-semibold">{{ t('admin.discounts.events.noProducts') }}</p>
            <p class="text-[13px] text-fg-muted">{{ t('admin.discounts.events.noProductsText') }}</p>
        </div>
        <p v-else-if="groups.length === 0" class="py-6 text-center text-sm text-fg-muted">
            {{ t('admin.discounts.events.noFilterMatches') }}
        </p>

        <div v-else class="flex flex-col gap-3">
            <div :class="['hidden gap-3 px-3 text-xs font-semibold text-fg-muted lg:grid', GRID]" aria-hidden="true">
                <span>{{ t('admin.discounts.events.columns.variant') }}</span>
                <span>{{ t('admin.discounts.events.columns.price') }}</span>
                <span>{{ t('admin.discounts.events.fields.percent') }}</span>
                <span>{{ t('admin.discounts.events.fields.off') }}</span>
                <span>{{ t('admin.discounts.events.fields.final') }}</span>
                <span>{{ t('admin.discounts.limit.column') }}</span>
                <span>{{ t('admin.discounts.events.columns.dates') }}</span>
                <span />
            </div>

            <article
                v-for="group in groups"
                :key="group.productId"
                class="overflow-hidden rounded-xl border border-line"
            >
                <header class="flex flex-wrap items-center gap-3 bg-surface-muted/50 px-3 py-2.5">
                    <input
                        v-if="canManage"
                        type="checkbox"
                        class="size-4 accent-[var(--color-primary)]"
                        :checked="group.shown.every((row) => selected.has(row.variantId))"
                        :aria-label="
                            t('admin.discounts.events.selectProduct', { name: group.rows[0]?.productName ?? '' })
                        "
                        @change="
                            toggle(
                                group.shown.map((row) => row.variantId),
                                !group.shown.every((row) => selected.has(row.variantId)),
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
                                ? t('admin.discounts.events.simpleProduct')
                                : t('admin.discounts.events.variantsInEvent', { n: formatNumber(group.rows.length) })
                        }}</span>
                    </span>
                    <AppButton
                        v-if="canManage && group.rows[0]?.productType === 'variable'"
                        variant="secondary"
                        size="md"
                        @click="addMissingVariants(group.productId)"
                    >
                        <AppIcon name="plus" :size="16" />
                        {{ t('admin.discounts.events.add.more') }}
                    </AppButton>
                </header>

                <ul class="divide-y divide-line">
                    <li
                        v-for="row in group.shown"
                        :key="row.variantId"
                        :class="[
                            'flex flex-col gap-2 px-3 py-3',
                            selected.has(row.variantId) && 'bg-primary-soft/40',
                            (!row.isEnabled || row.isDeleted) && 'opacity-70',
                        ]"
                    >
                        <div :class="['grid grid-cols-2 items-center gap-3 sm:grid-cols-3', GRID]">
                            <div class="col-span-2 flex min-w-0 items-center gap-2.5 sm:col-span-3 lg:col-span-1">
                                <input
                                    v-if="canManage"
                                    type="checkbox"
                                    class="size-4 shrink-0 accent-[var(--color-primary)]"
                                    :checked="selected.has(row.variantId)"
                                    :aria-label="
                                        t('admin.discounts.events.selectVariant', { name: row.label || row.sku })
                                    "
                                    @change="toggle([row.variantId], !selected.has(row.variantId))"
                                />
                                <span class="flex min-w-0 flex-col">
                                    <span class="truncate text-sm font-medium">{{
                                        row.label || t('admin.discounts.events.defaultVariant')
                                    }}</span>
                                    <span class="truncate text-xs text-fg-muted" dir="ltr">{{ row.sku }}</span>
                                </span>
                            </div>
                            <div class="text-sm">
                                <span class="text-xs text-fg-muted lg:hidden"
                                    >{{ t('admin.discounts.events.columns.price') }}:
                                </span>
                                {{ money.format(row.price) }}
                            </div>
                            <div :class="fieldClass(row, 'percent')">
                                <NumberField
                                    :model-value="linkedValues(row, money.rialsPerUnit.value).percent"
                                    :label="t('admin.discounts.events.fields.percent')"
                                    compact
                                    unit="%"
                                    :disabled="!canManage"
                                    @update:model-value="onType(row, 'percent', $event)"
                                />
                            </div>
                            <div :class="fieldClass(row, 'off')">
                                <NumberField
                                    :model-value="linkedValues(row, money.rialsPerUnit.value).off"
                                    :label="t('admin.discounts.events.fields.off')"
                                    compact
                                    :unit="money.unit.value"
                                    :factor="money.rialsPerUnit.value"
                                    :disabled="!canManage"
                                    @update:model-value="onType(row, 'off', $event)"
                                />
                            </div>
                            <div :class="fieldClass(row, 'final')">
                                <NumberField
                                    :model-value="linkedValues(row, money.rialsPerUnit.value).final"
                                    :label="t('admin.discounts.events.fields.final')"
                                    compact
                                    :unit="money.unit.value"
                                    :factor="money.rialsPerUnit.value"
                                    :disabled="!canManage"
                                    @update:model-value="onType(row, 'final', $event)"
                                />
                            </div>
                            <div
                                :class="limitInvalid(row.maxPerOrder) && 'rounded-lg ring-2 ring-danger'"
                                :title="t('admin.discounts.limit.hint')"
                            >
                                <NumberField
                                    :model-value="row.maxPerOrder"
                                    :label="t('admin.discounts.limit.column')"
                                    :placeholder="t('admin.discounts.limit.none')"
                                    compact
                                    :unit="t('admin.discounts.limit.unit')"
                                    :disabled="!canManage"
                                    @update:model-value="setLimit(row, $event)"
                                />
                            </div>
                            <button
                                type="button"
                                :disabled="!canManage"
                                :class="[
                                    'flex h-10 items-center gap-1.5 truncate rounded-lg border px-2.5 text-xs focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                                    row.startsAt || row.endsAt
                                        ? 'border-primary bg-primary-soft font-semibold text-fg'
                                        : 'border-dashed border-line-strong text-fg-muted hover:text-fg',
                                ]"
                                :title="t('admin.discounts.events.setDatesFor', { name: row.label || row.sku })"
                                @click="datesFor = new Set([row.variantId])"
                            >
                                <AppIcon name="clock" :size="14" class="shrink-0" />
                                <span class="truncate">{{ datesLabel(row) }}</span>
                            </button>
                            <button
                                v-if="canManage"
                                type="button"
                                class="flex size-10 items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-danger focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                :aria-label="t('admin.discounts.events.removeVariant', { name: row.label || row.sku })"
                                @click="remove(new Set([row.variantId]))"
                            >
                                <AppIcon name="close" :size="18" />
                            </button>
                        </div>
                        <p v-if="errorText(row)" class="flex items-start gap-1.5 text-[13px] text-danger">
                            <AppIcon name="alert" :size="16" class="mt-0.5 shrink-0" />
                            {{ errorText(row) }}
                        </p>
                        <p
                            v-if="note(row)"
                            :class="['rounded-lg px-3 py-2 text-[13px] leading-relaxed', noteClass[note(row)!.tone]]"
                        >
                            {{ note(row)!.text }}
                        </p>
                    </li>
                </ul>
            </article>
        </div>

        <p v-if="rows.length > 0" class="text-[13px] leading-relaxed text-fg-muted">
            {{
                money.rialsPerUnit.value > 1
                    ? t('admin.discounts.events.roundingToman')
                    : t('admin.discounts.events.roundingRial')
            }}
        </p>

        <template v-if="canManage">
            <AddProductsDialog
                :open="addOpen"
                :product-ids="productIds"
                :variant-ids="variantIds"
                @close="addOpen = false"
                @add="onAdd"
            />
            <SetDiscountDialog
                :open="discountOpen"
                :rows="selectedRows"
                @close="discountOpen = false"
                @apply="applyBulkDiscount"
            />
            <SetLimitDialog
                :open="limitOpen"
                :count="selected.size"
                @close="limitOpen = false"
                @apply="applyBulkLimit"
            />
            <SetDatesDialog
                :open="datesFor !== null"
                :count="datesFor?.size ?? 0"
                :starts-at="datesRow?.startsAt ?? ''"
                :ends-at="datesRow?.endsAt ?? ''"
                :event-starts-at="eventStartsAt"
                :event-ends-at="eventEndsAt"
                @close="datesFor = null"
                @apply="applyBulkDates"
            />
        </template>
    </section>
</template>
