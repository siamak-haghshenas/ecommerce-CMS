<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import ActionMenu, { type ActionMenuItem } from '@/shared/ui/ActionMenu.vue';
import AppBadge from '@/shared/ui/AppBadge.vue';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppPagination from '@/shared/ui/AppPagination.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SearchInput from '@/shared/ui/SearchInput.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { useMoney } from '../../settings/store';
import { couponPayloadOf, couponsApi } from '../api';
import DiscountTabs from '../components/DiscountTabs.vue';
import { TARGET_KINDS, usagePercent } from '../coupons';
import type { Coupon, CouponList, CouponState } from '../types';

/**
 * Sales › Discounts › Coupons: the codes customers enter at checkout, newest first, with search and
 * status chips (both in the URL). The row menu edits, copies the code, makes a copy, switches the
 * coupon on or off and deletes it (a used coupon can only be switched off).
 */
const { t } = useI18n();
const { formatNumber, formatDate } = useLocale();
const money = useMoney();
const toast = useToast();
const route = useRoute();
const router = useRouter();
const auth = useAdminAuthStore();
const canManage = computed(() => auth.can(Permission.ManageDiscounts));

const STATES = ['all', 'active', 'scheduled', 'used_up', 'expired', 'disabled'] as const;
type Filter = (typeof STATES)[number];

const queryString = (key: string): string => (typeof route.query[key] === 'string' ? route.query[key] : '');

const search = computed({
    get: () => queryString('q'),
    set: (q: string) => void router.replace({ query: { ...route.query, q: q || undefined, page: undefined } }),
});
const status = computed<Filter>({
    get: () => (STATES.includes(queryString('status') as Filter) ? (queryString('status') as Filter) : 'all'),
    set: (value) =>
        void router.replace({
            query: { ...route.query, status: value === 'all' ? undefined : value, page: undefined },
        }),
});
const page = computed(() => Math.max(1, Number(queryString('page')) || 1));

// ---- loading ----

const list = ref<CouponList | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        list.value = await couponsApi.list({
            q: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            page: page.value,
        });
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

watch(() => [search.value, status.value, page.value], load, { immediate: true });

function goTo(next: number): void {
    void router.replace({ query: { ...route.query, page: next > 1 ? String(next) : undefined } });
}

const filtering = computed(() => search.value !== '' || status.value !== 'all');

// ---- display ----

const stateTone = (state: CouponState): 'success' | 'warning' | 'danger' | 'neutral' =>
    state === 'active' ? 'success' : state === 'scheduled' ? 'warning' : state === 'used_up' ? 'danger' : 'neutral';

const chipClass = (on: boolean): string[] => [
    'inline-flex h-9 items-center gap-1.5 rounded-full border px-3.5 text-[13px] font-semibold focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
    on ? 'border-primary bg-primary-soft text-fg' : 'border-line text-fg-muted hover:text-fg',
];

function amount(coupon: Coupon): { main: string; note: string } {
    const notes: string[] = [];

    if (coupon.type === 'percent') {
        notes.push(
            coupon.max_discount === null
                ? t('admin.discounts.coupons.list.noCap')
                : t('admin.discounts.coupons.list.cap', { amount: money.format(coupon.max_discount) }),
        );
    } else if (coupon.type === 'fixed') {
        notes.push(t('admin.discounts.coupons.list.offOrder'));
    }

    if (coupon.exclude_discounted) {
        notes.push(t('admin.discounts.coupons.list.fullPriceOnly'));
    }

    const main =
        coupon.type === 'percent'
            ? t('admin.discounts.coupons.list.percentOff', { n: formatNumber(coupon.value ?? 0) })
            : coupon.type === 'fixed'
              ? money.format(coupon.value ?? 0)
              : t('admin.discounts.coupons.types.free_shipping');

    return { main, note: notes.join(' · ') };
}

function target(coupon: Coupon): string {
    const parts =
        coupon.applies_to === 'all'
            ? [t('admin.discounts.coupons.list.allProducts')]
            : [
                  TARGET_KINDS.filter((kind) => coupon.targets_count[kind] > 0)
                      .map((kind) =>
                          t(`admin.discounts.coupons.list.count.${kind}`, {
                              n: formatNumber(coupon.targets_count[kind]),
                          }),
                      )
                      .join(t('admin.discounts.separator')),
              ];

    if (coupon.customer_scope === 'selected') {
        parts.push(t('admin.discounts.coupons.list.customers', { n: formatNumber(coupon.customers_count) }));
    }

    return parts.join(' · ');
}

function condition(coupon: Coupon): string {
    const parts: string[] = [];

    if (coupon.min_order_amount !== null) {
        parts.push(t('admin.discounts.coupons.list.minOrder', { amount: money.format(coupon.min_order_amount) }));
    }

    if (coupon.first_order_only) {
        parts.push(t('admin.discounts.coupons.list.firstOrder'));
    }

    if (coupon.excluded_count > 0) {
        parts.push(t('admin.discounts.coupons.list.except', { n: formatNumber(coupon.excluded_count) }));
    }

    return parts.length > 0 ? parts.join(' · ') : '—';
}

const usage = (coupon: Coupon): string =>
    coupon.usage_limit === null
        ? t('admin.discounts.coupons.list.usedUnlimited', { n: formatNumber(coupon.used_count) })
        : t('admin.discounts.coupons.list.usedOf', {
              n: formatNumber(coupon.used_count),
              limit: formatNumber(coupon.usage_limit),
          });

function dates(coupon: Coupon): string {
    const start = coupon.starts_at ? formatDate(coupon.starts_at) : null;
    const end = coupon.ends_at ? formatDate(coupon.ends_at) : null;

    if (start && end) {
        return t('admin.discounts.window.between', { start, end });
    }

    if (end) {
        return t('admin.discounts.window.until', { end });
    }

    return start ? t('admin.discounts.window.from', { start }) : t('admin.discounts.coupons.list.always');
}

const barClass = (coupon: Coupon): string[] => [
    'block h-full rounded-full',
    coupon.state === 'used_up' ? 'bg-danger' : 'bg-primary',
    coupon.usage_limit === null ? 'opacity-25' : '',
];

const menuItems = (coupon: Coupon): ActionMenuItem[] => [
    { key: 'edit', label: t('admin.discounts.coupons.list.edit'), icon: 'pen' },
    { key: 'copy', label: t('admin.discounts.coupons.list.copyCode'), icon: 'copy' },
    ...(canManage.value
        ? [
              { key: 'duplicate', label: t('admin.discounts.coupons.list.duplicate'), icon: 'plus' as const },
              coupon.is_active
                  ? { key: 'toggle', label: t('admin.discounts.coupons.list.disable'), icon: 'ban' as const }
                  : { key: 'toggle', label: t('admin.discounts.coupons.list.enable'), icon: 'check' as const },
              {
                  key: 'delete',
                  label: t('admin.discounts.coupons.list.delete'),
                  icon: 'trash' as const,
                  danger: true,
                  separated: true,
              },
          ]
        : []),
];

// ---- actions ----

async function copyCode(coupon: Coupon): Promise<void> {
    try {
        await navigator.clipboard.writeText(coupon.code);
        toast.show(t('admin.discounts.coupons.list.copied', { code: coupon.code }));
    } catch {
        toast.show(t('admin.discounts.coupons.list.copyFailed'), 'danger');
    }
}

const deleting = ref<Coupon | null>(null);
const usedNotice = ref<Coupon | null>(null);
const busy = ref(false);

async function toggle(coupon: Coupon): Promise<void> {
    try {
        // A save sends the whole coupon, so it is loaded first.
        const detail = await couponsApi.show(coupon.id);
        const saved = await couponsApi.update(coupon.id, { ...couponPayloadOf(detail), is_active: !detail.is_active });
        toast.show(
            t(saved.is_active ? 'admin.discounts.coupons.list.enabled' : 'admin.discounts.coupons.list.disabled', {
                code: saved.code,
            }),
        );
        await load();
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    }
}

async function act(coupon: Coupon, action: string): Promise<void> {
    if (action === 'edit') {
        await router.push({ name: 'coupon', params: { id: coupon.id } });
    } else if (action === 'copy') {
        await copyCode(coupon);
    } else if (action === 'duplicate') {
        await router.push({ name: 'coupon-create', query: { from: coupon.id } });
    } else if (action === 'toggle') {
        await toggle(coupon);
    } else if (action === 'delete') {
        if (coupon.used_count > 0) {
            usedNotice.value = coupon;
        } else {
            deleting.value = coupon;
        }
    }
}

async function remove(): Promise<void> {
    const coupon = deleting.value;

    if (!coupon) {
        return;
    }

    busy.value = true;

    try {
        await couponsApi.remove(coupon.id);
        toast.show(t('admin.discounts.coupons.deleted', { code: coupon.code }));
        deleting.value = null;
        await load();
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
    } finally {
        busy.value = false;
    }
}

async function disableUsed(): Promise<void> {
    const coupon = usedNotice.value;
    usedNotice.value = null;

    if (coupon?.is_active) {
        await toggle(coupon);
    }
}

const GRID =
    'lg:grid lg:grid-cols-[minmax(0,11rem)_minmax(0,1fr)_minmax(0,11rem)_minmax(0,11rem)_8.5rem_minmax(0,10rem)_6.5rem_2.5rem] lg:items-center lg:gap-3.5';
</script>

<template>
    <div class="mx-auto flex max-w-7xl flex-col gap-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold">{{ t('admin.discounts.title') }}</h1>
                <p class="max-w-3xl text-sm text-fg-muted">{{ t('admin.discounts.coupons.intro') }}</p>
            </div>
            <AppButton v-if="canManage" size="md" @click="router.push({ name: 'coupon-create' })">
                <AppIcon name="plus" :size="18" />
                {{ t('admin.discounts.coupons.new') }}
            </AppButton>
        </div>

        <DiscountTabs active="coupons" />

        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-80">
                <SearchInput v-model="search" :label="t('admin.discounts.coupons.list.search')" />
            </div>
            <div class="flex flex-wrap gap-2" role="group" :aria-label="t('admin.discounts.statusLabel')">
                <button
                    v-for="which in STATES"
                    :key="which"
                    type="button"
                    :class="chipClass(status === which)"
                    :aria-pressed="status === which"
                    @click="status = which"
                >
                    {{
                        t(`admin.discounts.coupons.filters.${which}`, {
                            n: formatNumber(list?.meta.status_counts[which] ?? 0),
                        })
                    }}
                </button>
            </div>
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
            :class="[
                'overflow-hidden rounded-2xl border border-line bg-surface transition-opacity',
                loading && list && 'opacity-60',
            ]"
        >
            <div v-if="!list" aria-hidden="true" class="flex flex-col gap-px">
                <div
                    v-for="row in 5"
                    :key="row"
                    class="h-18 animate-pulse bg-surface-muted/60 motion-reduce:animate-none"
                />
            </div>

            <EmptyState
                v-else-if="list.data.length === 0"
                :icon="filtering ? 'search' : 'tag'"
                :title="
                    filtering ? t('admin.discounts.coupons.list.noResults') : t('admin.discounts.coupons.list.empty')
                "
                :description="filtering ? undefined : t('admin.discounts.coupons.list.emptyText')"
            >
                <AppButton v-if="canManage && !filtering" size="md" @click="router.push({ name: 'coupon-create' })">
                    <AppIcon name="plus" :size="18" />
                    {{ t('admin.discounts.coupons.new') }}
                </AppButton>
            </EmptyState>

            <template v-else>
                <div
                    :class="[
                        GRID,
                        'hidden bg-surface-muted/60 px-4.5 py-3 text-xs font-semibold text-fg-muted lg:grid',
                    ]"
                    aria-hidden="true"
                >
                    <span>{{ t('admin.discounts.coupons.columns.code') }}</span>
                    <span>{{ t('admin.discounts.coupons.columns.name') }}</span>
                    <span>{{ t('admin.discounts.coupons.columns.discount') }}</span>
                    <span>{{ t('admin.discounts.coupons.columns.target') }}</span>
                    <span>{{ t('admin.discounts.coupons.columns.usage') }}</span>
                    <span>{{ t('admin.discounts.coupons.columns.dates') }}</span>
                    <span>{{ t('admin.discounts.coupons.columns.status') }}</span>
                    <span />
                </div>
                <ul class="divide-y divide-line">
                    <li
                        v-for="coupon in list.data"
                        :key="coupon.id"
                        :class="[
                            GRID,
                            'flex flex-wrap items-center gap-x-4 gap-y-2 px-4.5 py-3.5 text-[13px] hover:bg-surface-muted/40',
                            !coupon.is_active && 'opacity-70',
                        ]"
                    >
                        <span class="flex min-w-0 items-center gap-1">
                            <span
                                dir="ltr"
                                class="truncate rounded-lg border border-dashed border-line-strong bg-surface-muted px-2 py-1 font-mono text-sm font-bold tracking-wide"
                                >{{ coupon.code }}</span
                            >
                            <button
                                type="button"
                                class="flex size-8 shrink-0 items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                :aria-label="t('admin.discounts.coupons.list.copyCodeOf', { code: coupon.code })"
                                @click="copyCode(coupon)"
                            >
                                <AppIcon name="copy" :size="15" />
                            </button>
                        </span>
                        <RouterLink
                            :to="{ name: 'coupon', params: { id: coupon.id } }"
                            class="min-w-0 grow basis-40 truncate rounded font-semibold text-fg hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none lg:grow-0"
                            >{{ coupon.name }}</RouterLink
                        >
                        <span class="flex min-w-0 basis-full flex-col gap-0.5 sm:basis-auto">
                            <span class="font-semibold">{{ amount(coupon).main }}</span>
                            <span v-if="amount(coupon).note" class="text-xs text-fg-muted">{{
                                amount(coupon).note
                            }}</span>
                        </span>
                        <span class="flex min-w-0 basis-full flex-col gap-0.5 sm:basis-auto">
                            <span>{{ target(coupon) }}</span>
                            <span class="text-xs text-fg-muted">{{ condition(coupon) }}</span>
                        </span>
                        <span class="flex w-34 flex-col gap-1.5">
                            <span class="text-xs">{{ usage(coupon) }}</span>
                            <span class="h-1.5 overflow-hidden rounded-full bg-surface-muted" aria-hidden="true">
                                <span
                                    :class="barClass(coupon)"
                                    :style="{ width: `${usagePercent(coupon.used_count, coupon.usage_limit) ?? 100}%` }"
                                />
                            </span>
                        </span>
                        <span class="text-xs text-fg-muted">{{ dates(coupon) }}</span>
                        <AppBadge :tone="stateTone(coupon.state)" class="w-fit shrink-0">{{
                            t(`admin.discounts.coupons.states.${coupon.state}`)
                        }}</AppBadge>
                        <ActionMenu
                            :label="t('admin.discounts.coupons.list.actionsFor', { code: coupon.code })"
                            :items="menuItems(coupon)"
                            class="ms-auto lg:ms-0"
                            @select="act(coupon, $event)"
                        />
                    </li>
                </ul>
                <AppPagination v-if="list.meta.last_page > 1" :meta="list.meta" @change="goTo" />
            </template>
        </section>

        <AppDialog
            :open="deleting !== null"
            :title="t('admin.discounts.coupons.deleteTitle', { code: deleting?.code ?? '' })"
            icon="trash"
            tone="danger"
            :busy="busy"
            @close="deleting = null"
        >
            <p class="text-sm leading-loose">{{ t('admin.discounts.coupons.deleteText') }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" :loading="busy" @click="remove">{{
                    t('admin.discounts.coupons.deleteConfirm')
                }}</AppButton>
                <AppButton variant="secondary" size="md" :disabled="busy" @click="deleting = null">{{
                    t('common.cancel')
                }}</AppButton>
            </template>
        </AppDialog>

        <AppDialog
            :open="usedNotice !== null"
            :title="t('admin.discounts.coupons.usedTitle', { code: usedNotice?.code ?? '' })"
            icon="info"
            @close="usedNotice = null"
        >
            <p class="text-sm leading-loose">
                {{ t('admin.discounts.coupons.usedText', { n: formatNumber(usedNotice?.used_count ?? 0) }) }}
            </p>
            <template #footer>
                <AppButton v-if="usedNotice?.is_active" size="md" @click="disableUsed">{{
                    t('admin.discounts.coupons.list.disable')
                }}</AppButton>
                <AppButton variant="secondary" size="md" @click="usedNotice = null">{{ t('common.close') }}</AppButton>
            </template>
        </AppDialog>
    </div>
</template>
