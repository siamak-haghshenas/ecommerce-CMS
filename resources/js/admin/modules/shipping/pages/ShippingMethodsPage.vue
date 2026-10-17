<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import ActionMenu, { type ActionMenuItem } from '@/shared/ui/ActionMenu.vue';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon, { type IconName } from '@/shared/ui/AppIcon.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SearchInput from '@/shared/ui/SearchInput.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import SelectField from '@/shared/ui/SelectField.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { useMoney, useSettingsStore } from '../../settings/store';
import { shippingApi, shippingPayloadOf } from '../api';
import NewShippingMethodDialog from '../components/NewShippingMethodDialog.vue';
import { feeRange, measureLimit, MEASURE_FACTOR } from '../shipping';
import type { ShippingMethod, ShippingMethodList, ShippingType } from '../types';

/**
 * Sales › Shipping: every method in the order customers see them at checkout. Dragging or
 * Move up / Move down saves the order; search, status and type filter in the browser.
 */
const { t } = useI18n();
const { formatNumber } = useLocale();
const money = useMoney();
const settings = useSettingsStore();
const toast = useToast();
const route = useRoute();
const router = useRouter();
const auth = useAdminAuthStore();
const canManage = computed(() => auth.can(Permission.ManageShipping));

const methods = ref<ShippingMethod[] | null>(null);
const unmeasured = ref<ShippingMethodList['meta']['unmeasured_products'] | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        const list = await shippingApi.list();
        methods.value = list.data;
        unmeasured.value = list.meta.unmeasured_products;
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

void load();

// ---- filters (in the URL) ----

const queryString = (key: string): string => (typeof route.query[key] === 'string' ? route.query[key] : '');

const STATUSES = ['all', 'active', 'inactive'] as const;
const TYPES = ['all', 'weight', 'volume', 'courier'] as const;

const search = computed({
    get: () => queryString('q'),
    set: (q: string) => void router.replace({ query: { ...route.query, q: q || undefined } }),
});
const status = computed<(typeof STATUSES)[number]>({
    get: () =>
        STATUSES.includes(queryString('status') as (typeof STATUSES)[number])
            ? (queryString('status') as (typeof STATUSES)[number])
            : 'all',
    set: (value) => void router.replace({ query: { ...route.query, status: value === 'all' ? undefined : value } }),
});
const type = computed<(typeof TYPES)[number]>({
    get: () =>
        TYPES.includes(queryString('type') as (typeof TYPES)[number])
            ? (queryString('type') as (typeof TYPES)[number])
            : 'all',
    set: (value) => void router.replace({ query: { ...route.query, type: value === 'all' ? undefined : value } }),
});

const statusOptions = computed(() =>
    STATUSES.map((value) => ({ value, label: t(`admin.shipping.statuses.${value}`) })),
);
const typeOptions = computed(() =>
    TYPES.map((value) => ({
        value,
        label: value === 'all' ? t('admin.shipping.statuses.all') : t(`admin.shipping.types.${value}`),
    })),
);

const filtering = computed(() => search.value.trim() !== '' || status.value !== 'all' || type.value !== 'all');
const filtered = computed(() => {
    const query = search.value.trim().toLowerCase();

    return (methods.value ?? []).filter(
        (method) =>
            (status.value === 'all' || method.is_active === (status.value === 'active')) &&
            (type.value === 'all' || method.type === type.value) &&
            (query === '' || method.name.toLowerCase().includes(query)),
    );
});
/** Checkout position as shown: 1 is the first. */
const rank = (method: ShippingMethod): number => (methods.value ?? []).findIndex((item) => item.id === method.id) + 1;

// ---- order ----

async function reorder(ids: number[]): Promise<void> {
    try {
        methods.value = await shippingApi.reorder(ids);
        toast.show(t('admin.shipping.orderSaved'));
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
        await load();
    }
}

async function onDrop(): Promise<void> {
    // Let the list model catch up with the drop before reading the new order.
    await nextTick();
    void reorder((methods.value ?? []).map((method) => method.id));
}

function move(method: ShippingMethod, offset: -1 | 1): void {
    const ids = (methods.value ?? []).map((item) => item.id);
    const index = ids.indexOf(method.id);
    const target = index + offset;

    if (index === -1 || target < 0 || target >= ids.length) {
        return;
    }

    [ids[index], ids[target]] = [ids[target] as number, method.id];
    void reorder(ids);
}

// ---- display ----

const TYPE_ICONS: Record<ShippingType, IconName> = { weight: 'scale', volume: 'box', courier: 'truck' };

function feeText(method: ShippingMethod): string {
    const range = feeRange(method);

    if (!range) {
        return '—';
    }

    return range.min === range.max
        ? money.format(range.min)
        : t('admin.shipping.list.feeBetween', { min: money.format(range.min), max: money.format(range.max) });
}

function feeNote(method: ShippingMethod): string {
    if (method.type === 'courier') {
        return t('admin.shipping.list.cityCount', { n: formatNumber(method.cities.length) });
    }

    const limit = measureLimit(method);
    const unit = method.type === 'weight' ? t('admin.shipping.units.kg') : t('admin.shipping.units.litres');

    return [
        t('admin.shipping.list.rangeCount', { n: formatNumber(method.rates.length) }),
        limit === null
            ? t('admin.shipping.list.noLimit')
            : t('admin.shipping.list.upTo', { n: formatNumber(limit / MEASURE_FACTOR), unit }),
    ].join(' · ');
}

function areaText(method: ShippingMethod): string {
    if (method.type !== 'courier') {
        return t('admin.shipping.list.allIran');
    }

    const names = method.cities.map((city) => city.name);
    const shown = names.slice(0, 2).join(t('admin.shipping.list.listSeparator'));

    return names.length > 2
        ? t('admin.shipping.list.andMore', { names: shown, n: formatNumber(names.length - 2) })
        : shown || '—';
}

function daysText(method: ShippingMethod): string | null {
    const { delivery_days_min: min, delivery_days_max: max } = method;

    if (min === null && max === null) {
        return null;
    }

    if (min !== null && max !== null && min !== max) {
        return t('admin.shipping.days.between', { min: formatNumber(min), max: formatNumber(max) });
    }

    const days = (min ?? max) as number;

    return days === 0 ? t('admin.shipping.days.sameDay') : t('admin.shipping.days.exactly', { n: formatNumber(days) });
}

const menuItems = (method: ShippingMethod): ActionMenuItem[] => {
    const index = rank(method) - 1;
    const last = (methods.value?.length ?? 0) - 1;

    return [
        { key: 'edit', label: t('admin.shipping.list.edit'), icon: 'pen' },
        ...(canManage.value
            ? [
                  { key: 'copy', label: t('admin.shipping.list.copy'), icon: 'copy' as const },
                  ...(!filtering.value && index > 0
                      ? [{ key: 'up', label: t('admin.shipping.list.moveUp'), icon: 'arrowUp' as const }]
                      : []),
                  ...(!filtering.value && index < last
                      ? [{ key: 'down', label: t('admin.shipping.list.moveDown'), icon: 'arrowDown' as const }]
                      : []),
                  {
                      key: 'delete',
                      label: t('admin.shipping.list.delete'),
                      icon: 'trash' as const,
                      danger: true,
                      separated: true,
                  },
              ]
            : []),
    ];
};

// ---- actions ----

const creating = ref(false);
const deleting = ref<ShippingMethod | null>(null);
const busy = ref(false);
const toggling = ref<number | null>(null);

function create(name: string, methodType: ShippingType): void {
    creating.value = false;
    void router.push({ name: 'shipping-method-create', query: { type: methodType, name } });
}

async function act(method: ShippingMethod, action: string): Promise<void> {
    if (action === 'edit') {
        await router.push({ name: 'shipping-method', params: { id: method.id } });
    } else if (action === 'copy') {
        await router.push({ name: 'shipping-method-create', query: { from: method.id } });
    } else if (action === 'up' || action === 'down') {
        move(method, action === 'up' ? -1 : 1);
    } else if (action === 'delete') {
        deleting.value = method;
    }
}

async function toggleActive(method: ShippingMethod): Promise<void> {
    toggling.value = method.id;

    try {
        const saved = await shippingApi.update(method.id, {
            ...shippingPayloadOf(method),
            is_active: !method.is_active,
        });
        methods.value = (methods.value ?? []).map((item) => (item.id === saved.id ? saved : item));
        toast.show(
            t(saved.is_active ? 'admin.shipping.list.enabled' : 'admin.shipping.list.disabled', { name: saved.name }),
        );
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    } finally {
        toggling.value = null;
    }
}

async function remove(): Promise<void> {
    const method = deleting.value;

    if (!method) {
        return;
    }

    busy.value = true;

    try {
        await shippingApi.remove(method.id);
        toast.show(t('admin.shipping.list.deleted', { name: method.name }));
        deleting.value = null;
        await load();
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    } finally {
        busy.value = false;
    }
}

const warningText = computed(() => {
    const counts = unmeasured.value;

    if (!counts || (counts.missing_weight === 0 && counts.missing_dimensions === 0)) {
        return null;
    }

    const grams = settings.settings.default_weight_grams;
    const parts: string[] = [];

    if (counts.missing_weight > 0) {
        parts.push(
            grams === null
                ? t('admin.shipping.warning.weightNoDefault', { n: formatNumber(counts.missing_weight) })
                : t('admin.shipping.warning.weightDefault', {
                      n: formatNumber(counts.missing_weight),
                      grams: formatNumber(grams),
                  }),
        );
    }

    if (counts.missing_dimensions > 0) {
        parts.push(t('admin.shipping.warning.dimensions', { n: formatNumber(counts.missing_dimensions) }));
    }

    return parts.join(' ');
});

const chipClass =
    'inline-flex h-6.5 w-fit shrink-0 items-center gap-1.5 rounded-lg px-2.5 text-xs font-semibold whitespace-nowrap';
</script>

<template>
    <div class="mx-auto flex max-w-7xl flex-col gap-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold">{{ t('admin.shipping.title') }}</h1>
                <p class="max-w-3xl text-sm text-fg-muted">{{ t('admin.shipping.intro') }}</p>
            </div>
            <AppButton v-if="canManage" size="md" @click="creating = true">
                <AppIcon name="plus" :size="18" />
                {{ t('admin.shipping.new') }}
            </AppButton>
        </div>

        <div
            v-if="warningText"
            role="note"
            class="flex flex-wrap items-start gap-3 rounded-xl border border-warning/40 bg-warning-soft px-4 py-3 text-[13px]"
        >
            <AppIcon name="alert" :size="18" class="mt-0.5 shrink-0 text-warning" />
            <span class="min-w-0 grow basis-64 leading-relaxed">{{ warningText }}</span>
            <span class="flex flex-wrap gap-x-4 gap-y-1 font-semibold">
                <RouterLink
                    v-if="unmeasured && unmeasured.missing_weight > 0"
                    :to="{ name: 'products', query: { shipping: 'missing_weight' } }"
                    class="rounded text-primary hover:underline focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    >{{ t('admin.shipping.warning.showWeight') }}</RouterLink
                >
                <RouterLink
                    v-if="unmeasured && unmeasured.missing_dimensions > 0"
                    :to="{ name: 'products', query: { shipping: 'missing_dimensions' } }"
                    class="rounded text-primary hover:underline focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    >{{ t('admin.shipping.warning.showDimensions') }}</RouterLink
                >
            </span>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <SearchInput v-model="search" :label="t('admin.shipping.search')" />
            <div class="w-full sm:w-auto sm:min-w-80">
                <SegmentedControl v-model="status" :label="t('admin.shipping.statusLabel')" :options="statusOptions" />
            </div>
            <SelectField v-model="type" :label="t('admin.shipping.typeLabel')" :options="typeOptions" />
        </div>
        <p v-if="canManage && methods && methods.length > 1" class="text-[13px] text-fg-muted">
            {{ t('admin.shipping.orderHint') }}
        </p>

        <div v-if="error" class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="grow"><FormAlert :message="error" /></div>
            <AppButton variant="secondary" size="md" :loading="loading" @click="load">{{
                t('common.retry')
            }}</AppButton>
        </div>

        <section
            v-else
            :aria-busy="loading"
            :class="[
                'rounded-2xl border border-line bg-surface transition-opacity',
                loading && methods && 'opacity-60',
            ]"
        >
            <div v-if="!methods" aria-hidden="true" class="flex flex-col gap-px overflow-hidden rounded-2xl">
                <div
                    v-for="row in 4"
                    :key="row"
                    class="h-18 animate-pulse bg-surface-muted/60 motion-reduce:animate-none"
                />
            </div>

            <EmptyState
                v-else-if="filtered.length === 0"
                :icon="filtering ? 'search' : 'truck'"
                :title="filtering ? t('admin.shipping.noResults') : t('admin.shipping.empty')"
                :description="filtering ? undefined : t('admin.shipping.emptyText')"
            >
                <AppButton v-if="canManage && !filtering" size="md" @click="creating = true">
                    <AppIcon name="plus" :size="18" />
                    {{ t('admin.shipping.new') }}
                </AppButton>
            </EmptyState>

            <VueDraggable
                v-else
                v-model="methods"
                tag="ul"
                handle=".drag-handle"
                ghost-class="method-drop-ghost"
                :animation="150"
                :disabled="!canManage || filtering"
                class="flex flex-col divide-y divide-line"
                @update="onDrop"
            >
                <li
                    v-for="method in filtered"
                    :key="method.id"
                    :class="[
                        'flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3.5 hover:bg-surface-muted/40 lg:flex-nowrap',
                        !method.is_active && 'bg-surface-muted/30',
                    ]"
                >
                    <button
                        v-if="canManage && !filtering && methods.length > 1"
                        type="button"
                        class="drag-handle flex h-10 w-8 shrink-0 cursor-grab items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                        :aria-label="t('admin.shipping.list.dragHandle', { name: method.name })"
                    >
                        <AppIcon name="grip" :size="18" />
                    </button>
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-surface-muted text-[13px] font-bold text-fg-muted"
                        :title="t('admin.shipping.list.position', { n: formatNumber(rank(method)) })"
                        >{{ formatNumber(rank(method)) }}</span
                    >
                    <span class="flex min-w-0 grow basis-48 flex-col gap-0.5">
                        <RouterLink
                            :to="{ name: 'shipping-method', params: { id: method.id } }"
                            :class="[
                                'truncate rounded font-semibold hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                                method.is_active ? 'text-fg' : 'text-fg-muted',
                            ]"
                        >
                            {{ method.name }}
                        </RouterLink>
                        <span v-if="daysText(method)" class="truncate text-xs text-fg-muted">{{
                            daysText(method)
                        }}</span>
                    </span>
                    <span :class="[chipClass, 'bg-surface-muted text-fg']">
                        <AppIcon :name="TYPE_ICONS[method.type]" :size="14" />
                        {{ t(`admin.shipping.types.${method.type}`) }}
                    </span>
                    <span class="flex w-48 shrink-0 flex-col gap-0.5">
                        <span class="text-[13px] font-semibold">{{ feeText(method) }}</span>
                        <span class="truncate text-xs text-fg-muted">{{ feeNote(method) }}</span>
                    </span>
                    <span class="flex w-44 shrink-0 items-center gap-1.5 text-[13px] text-fg-muted">
                        <AppIcon name="mapPin" :size="14" class="shrink-0" />
                        <span class="truncate">{{ areaText(method) }}</span>
                    </span>
                    <span
                        :class="[
                            chipClass,
                            method.fee_on_delivery ? 'bg-warning-soft text-warning' : 'bg-surface-muted text-fg-muted',
                        ]"
                        >{{
                            method.fee_on_delivery
                                ? t('admin.shipping.payment.onDelivery')
                                : t('admin.shipping.payment.withOrder')
                        }}</span
                    >
                    <span class="flex w-44 shrink-0 flex-col gap-0.5 text-xs text-fg-muted">
                        <span>{{
                            method.free_over_amount === null
                                ? t('admin.shipping.list.neverFree')
                                : t('admin.shipping.list.freeOver', { amount: money.format(method.free_over_amount) })
                        }}</span>
                        <span v-if="method.min_order_amount !== null">{{
                            t('admin.shipping.list.minOrder', { amount: money.format(method.min_order_amount) })
                        }}</span>
                    </span>
                    <label
                        class="flex shrink-0 cursor-pointer items-center gap-2 text-[13px] font-semibold"
                        :title="method.is_active ? t('admin.shipping.list.active') : t('admin.shipping.list.inactive')"
                    >
                        <input
                            type="checkbox"
                            role="switch"
                            class="size-4 accent-[var(--color-primary)]"
                            :checked="method.is_active"
                            :disabled="!canManage || toggling === method.id"
                            :aria-label="t('admin.shipping.list.activeFor', { name: method.name })"
                            @change="toggleActive(method)"
                        />
                        <span class="lg:sr-only">{{
                            method.is_active ? t('admin.shipping.list.active') : t('admin.shipping.list.inactive')
                        }}</span>
                    </label>
                    <ActionMenu
                        :label="t('admin.shipping.list.actionsFor', { name: method.name })"
                        :items="menuItems(method)"
                        @select="act(method, $event)"
                    />
                </li>
            </VueDraggable>
        </section>

        <p v-if="methods && methods.length > 0" class="text-xs leading-relaxed text-fg-muted">
            {{ t('admin.shipping.list.footnote') }}
        </p>

        <NewShippingMethodDialog :open="creating" @close="creating = false" @create="create" />

        <AppDialog
            :open="deleting !== null"
            :title="t('admin.shipping.deleteTitle', { name: deleting?.name ?? '' })"
            icon="trash"
            tone="danger"
            :busy="busy"
            @close="deleting = null"
        >
            <p class="text-sm leading-loose">{{ t('admin.shipping.deleteText') }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" :loading="busy" @click="remove">{{
                    t('admin.shipping.deleteConfirm')
                }}</AppButton>
                <AppButton variant="secondary" size="md" :disabled="busy" @click="deleting = null">{{
                    t('common.cancel')
                }}</AppButton>
            </template>
        </AppDialog>
    </div>
</template>

<style scoped>
.method-drop-ghost {
    opacity: 0.4;
}
</style>
