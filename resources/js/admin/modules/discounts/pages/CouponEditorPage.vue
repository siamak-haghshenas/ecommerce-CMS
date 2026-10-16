<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { onBeforeRouteLeave, useRouter } from 'vue-router';
import { useApiForm } from '@/shared/composables/useApiForm';
import { useLocale } from '@/shared/composables/useLocale';
import { useToast } from '@/shared/composables/useToast';
import { toApiError } from '@/shared/http/errors';
import AppBadge from '@/shared/ui/AppBadge.vue';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import NumberField from '@/shared/ui/NumberField.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import TextField from '@/shared/ui/TextField.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import ProductPickerDialog, { type PickedProduct } from '../../products/components/ProductPickerDialog.vue';
import { fromLocalInput, toLocalInput } from '../../products/variants';
import { useMoney } from '../../settings/store';
import { couponsApi } from '../api';
import CouponCustomersDialog from '../components/CouponCustomersDialog.vue';
import CouponSampleOrder from '../components/CouponSampleOrder.vue';
import CouponTargetsDialog from '../components/CouponTargetsDialog.vue';
import {
    type CouponForm,
    couponProblems,
    emptyForm,
    formFromCoupon,
    generateCode,
    MAX_PERCENT,
    mergeLinks,
    normalizeCode,
    TARGET_KINDS,
    toCouponPayload,
    usagePercent,
} from '../coupons';
import type { CouponCustomer, CouponDetail, CouponTargetKind, CouponTargets, SaveCouponPayload } from '../types';

/**
 * Create (`id` null; `copyFrom` starts from another coupon's settings) or edit a coupon: its code,
 * the discount, what it applies to, who may use it, its limits and dates. The sample order beside
 * the form shows what the unsaved coupon does to a cart.
 */
const props = defineProps<{ id: number | null; copyFrom: number | null }>();

const { t } = useI18n();
const { formatNumber, formatRelativeTime, localizeDigits } = useLocale();
const money = useMoney();
const toast = useToast();
const router = useRouter();
const auth = useAdminAuthStore();
const canManage = computed(() => auth.can(Permission.ManageDiscounts));

const FIELDS = [
    'code',
    'name',
    'type',
    'value',
    'max_discount',
    'min_order_amount',
    'applies_to',
    'category_ids',
    'brand_ids',
    'tag_ids',
    'product_ids',
    'excluded_product_ids',
    'exclude_discounted',
    'customer_scope',
    'customer_ids',
    'usage_limit',
    'per_customer_limit',
    'first_order_only',
    'starts_at',
    'ends_at',
    'is_active',
] as const;
const form = useApiForm(FIELDS);

const coupon = ref<CouponDetail | null>(null);
const loading = ref(true);
const loadError = ref<string | null>(null);
const fields = ref<CouponForm>(emptyForm(toLocalInput(new Date().toISOString())));

const payload = (): SaveCouponPayload => toCouponPayload(fields.value, fromLocalInput);
const snapshot = ref('');

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;

    try {
        if (props.id !== null) {
            coupon.value = await couponsApi.show(props.id);
            fields.value = formFromCoupon(coupon.value, toLocalInput);
            snapshot.value = JSON.stringify(payload());
        } else {
            const now = toLocalInput(new Date().toISOString());
            snapshot.value = JSON.stringify(toCouponPayload(emptyForm(now), fromLocalInput));

            if (props.copyFrom !== null) {
                const source = await couponsApi.show(props.copyFrom);
                fields.value = {
                    ...formFromCoupon(source, toLocalInput),
                    code: '',
                    name: t('admin.discounts.coupons.copyName', { name: source.name }),
                    startsAt: now,
                };
            } else {
                fields.value = emptyForm(now);
            }
        }
    } catch (caught) {
        loadError.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

onMounted(load);

const dirty = computed(
    () => canManage.value && !loading.value && snapshot.value !== '' && JSON.stringify(payload()) !== snapshot.value,
);

// ---- fields ----

const typeOptions = computed(() =>
    (['percent', 'fixed', 'free_shipping'] as const).map((value) => ({
        value,
        label: t(`admin.discounts.coupons.types.${value}`),
    })),
);
const appliesOptions = computed(() => [
    { value: 'all' as const, label: t('admin.discounts.coupons.applies.all') },
    { value: 'selected' as const, label: t('admin.discounts.coupons.applies.selected') },
]);
const customerOptions = computed(() => [
    { value: 'all' as const, label: t('admin.discounts.coupons.who.all') },
    { value: 'selected' as const, label: t('admin.discounts.coupons.who.selected') },
]);

function onCodeInput(value: string): void {
    fields.value.code = normalizeCode(value);
}

function generate(): void {
    fields.value.code = generateCode();
}

const included = computed(() =>
    TARGET_KINDS.flatMap((kind) => fields.value.targets[kind].map((link) => ({ kind, link }))),
);

function removeTarget(kind: CouponTargetKind, id: number): void {
    fields.value.targets = {
        ...fields.value.targets,
        [kind]: fields.value.targets[kind].filter((link) => link.id !== id),
    };
}

const targetsOpen = ref(false);

function setTargets(targets: CouponTargets): void {
    fields.value.targets = targets;
    targetsOpen.value = false;
}

const excludeOpen = ref(false);

function setExcluded(products: PickedProduct[]): void {
    fields.value.excluded = products;
    excludeOpen.value = false;
}

const customersOpen = ref(false);
const showAllCustomers = ref(false);
const SHOWN_CUSTOMERS = 5;
const chosenCustomerIds = computed(() => new Set(fields.value.customers.map((customer) => customer.id)));
const shownCustomers = computed(() =>
    showAllCustomers.value ? fields.value.customers : fields.value.customers.slice(0, SHOWN_CUSTOMERS),
);

function addCustomers(customers: CouponCustomer[]): void {
    fields.value.customers = mergeLinks(fields.value.customers, customers);
    customersOpen.value = false;
}

function removeCustomer(id: number): void {
    fields.value.customers = fields.value.customers.filter((customer) => customer.id !== id);
}

// ---- checks and errors ----

/** Client checks show once a save was tried, then follow the typing. */
const attempted = ref(false);
const problems = computed(() => couponProblems(fields.value));
const valid = computed(() => Object.keys(problems.value).length === 0);

function problemText(field: keyof SaveCouponPayload): string | undefined {
    const problem = problems.value[field];

    if (!problem) {
        return undefined;
    }

    return t(`admin.discounts.coupons.problems.${problem}`, {
        min: formatNumber(3),
        max: formatNumber(MAX_PERCENT),
    });
}

const errorOf = (field: (typeof FIELDS)[number]): string | undefined =>
    form.errors[field] ?? (attempted.value ? problemText(field) : undefined);

/** Errors of linked items (`category_ids.2`, ...) belong to their box. */
const fieldErrors = computed(() => form.lastError.value?.fieldErrors ?? {});
const listErrorOf = (prefix: string): string | undefined =>
    Object.entries(fieldErrors.value).find(([key]) => key.startsWith(`${prefix}.`))?.[1]?.[0];
const targetsError = computed(
    () =>
        errorOf('applies_to') ??
        ['category_ids', 'brand_ids', 'tag_ids', 'product_ids']
            .map((field) => form.errors[field as (typeof FIELDS)[number]] ?? listErrorOf(field))
            .find(Boolean),
);
const excludedError = computed(() => errorOf('excluded_product_ids') ?? listErrorOf('excluded_product_ids'));
const customersError = computed(() => errorOf('customer_ids') ?? listErrorOf('customer_ids'));

const alertMessage = computed(() => {
    const keys = Object.keys(fieldErrors.value);
    const placed = (key: string): boolean => (FIELDS as readonly string[]).includes(key.split('.')[0] ?? key);

    return keys.length > 0 && keys.every(placed) && form.lastError.value?.code === 'validation_failed'
        ? null
        : form.generalError.value;
});

// ---- saving, deleting ----

async function save(): Promise<void> {
    attempted.value = true;

    if (!valid.value) {
        toast.show(t('admin.discounts.coupons.fixProblems'), 'danger');

        return;
    }

    const existing = coupon.value;
    const body = payload();
    const saved = await form.submit(() => (existing ? couponsApi.update(existing.id, body) : couponsApi.create(body)));

    if (!saved) {
        toast.show(t('admin.discounts.coupons.saveFailed'), 'danger');

        return;
    }

    coupon.value = saved;
    fields.value = formFromCoupon(saved, toLocalInput);
    snapshot.value = JSON.stringify(payload());
    attempted.value = false;
    toast.show(t(existing ? 'admin.discounts.coupons.saved' : 'admin.discounts.coupons.created', { code: saved.code }));

    if (!existing) {
        await router.replace({ name: 'coupon', params: { id: saved.id } });
    }
}

const confirmDelete = ref(false);
const deleting = ref(false);

async function remove(): Promise<void> {
    if (!coupon.value) {
        return;
    }

    deleting.value = true;

    try {
        await couponsApi.remove(coupon.value.id);
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
        deleting.value = false;

        return;
    }

    snapshot.value = JSON.stringify(payload());
    toast.show(t('admin.discounts.coupons.deleted', { code: coupon.value.code }));
    await router.push({ name: 'coupons' });
}

// ---- leaving with unsaved changes ----

const leaveTarget = ref<null | (() => void)>(null);
let leaveCancel: (() => void) | null = null;

onBeforeRouteLeave(
    (to) =>
        new Promise<boolean>((resolve) => {
            if (!dirty.value || (to.name === 'coupon' && coupon.value !== null)) {
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

// ---- page ----

const stateTone = computed(() => {
    const state = coupon.value?.state;

    return state === 'active'
        ? 'success'
        : state === 'scheduled'
          ? 'warning'
          : state === 'used_up'
            ? 'danger'
            : 'neutral';
});

const headerDetail = computed(() => {
    const saved = coupon.value;

    if (!saved) {
        return null;
    }

    const parts: string[] = [];

    if (saved.state === 'scheduled' && saved.starts_at) {
        parts.push(t('admin.discounts.coupons.startsIn', { time: formatRelativeTime(saved.starts_at) }));
    } else if (saved.state === 'active' && saved.ends_at) {
        parts.push(t('admin.discounts.coupons.endsIn', { time: formatRelativeTime(saved.ends_at) }));
    }

    parts.push(t('admin.discounts.coupons.usedTimes', { n: formatNumber(saved.used_count) }));

    return parts.join(' · ');
});

/** What the dates and the switch mean right now, as typed. */
const scheduleNote = computed(() => {
    const { isActive, startsAt, endsAt } = fields.value;
    const start = fromLocalInput(startsAt);
    const end = fromLocalInput(endsAt);
    const now = Date.now();

    if (!isActive) {
        return t('admin.discounts.coupons.schedule.off');
    }

    if (end && new Date(end).getTime() <= now) {
        return t('admin.discounts.coupons.schedule.ended');
    }

    if (start && new Date(start).getTime() > now) {
        return t('admin.discounts.coupons.schedule.starts', { time: formatRelativeTime(start) });
    }

    return end
        ? t('admin.discounts.coupons.schedule.runningUntil', { time: formatRelativeTime(end) })
        : t('admin.discounts.coupons.schedule.runningForever');
});

const usageLeft = computed(() =>
    coupon.value && coupon.value.usage_limit !== null
        ? Math.max(0, coupon.value.usage_limit - coupon.value.used_count)
        : null,
);

const cardClass = 'flex flex-col gap-4.5 rounded-2xl border border-line bg-surface p-5 sm:p-6';
const boxClass = 'flex flex-col gap-3 rounded-xl border border-line p-3.5';
const dateClass =
    'h-13 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-[15px] text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft';
const smallButtonClass =
    'flex h-9 shrink-0 items-center gap-1.5 rounded-[10px] border border-line-strong px-3 text-[13px] font-semibold hover:bg-surface-muted focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none disabled:opacity-50';
const chipRemoveClass =
    'flex size-6 items-center justify-center rounded-full text-fg-muted hover:bg-surface hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none';
</script>

<template>
    <div class="mx-auto flex max-w-7xl flex-col gap-5">
        <RouterLink
            :to="{ name: 'coupons' }"
            class="flex w-fit items-center gap-1.5 rounded-lg text-sm font-medium text-fg-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
        >
            <AppIcon name="arrowForward" :size="16" flip-in-rtl class="rotate-180" />
            {{ t('admin.discounts.coupons.backToList') }}
        </RouterLink>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 flex-col gap-1">
                <div class="flex min-w-0 flex-wrap items-center gap-2.5">
                    <h1 class="truncate text-2xl font-bold">
                        {{ coupon ? coupon.name : t('admin.discounts.coupons.newTitle') }}
                    </h1>
                    <AppBadge v-if="coupon" :tone="stateTone">{{
                        t(`admin.discounts.coupons.states.${coupon.state}`)
                    }}</AppBadge>
                </div>
                <p v-if="headerDetail" class="text-xs text-fg-muted">{{ headerDetail }}</p>
            </div>
            <div v-if="coupon && canManage" class="flex flex-col items-end gap-1">
                <AppButton
                    variant="danger-outline"
                    size="md"
                    :disabled="coupon.used_count > 0"
                    @click="confirmDelete = true"
                >
                    <AppIcon name="trash" :size="18" />
                    {{ t('admin.discounts.coupons.list.delete') }}
                </AppButton>
                <span v-if="coupon.used_count > 0" class="max-w-64 text-end text-xs text-fg-muted">{{
                    t('admin.discounts.coupons.cantDelete')
                }}</span>
            </div>
        </div>

        <div v-if="loading" class="flex justify-center py-16"><AppSpinner /></div>
        <div v-else-if="loadError" class="flex flex-col gap-3">
            <FormAlert :message="loadError" />
            <div>
                <AppButton variant="secondary" size="md" @click="load">{{ t('common.retry') }}</AppButton>
            </div>
        </div>

        <div v-else class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <form id="coupon-form" class="flex min-w-0 flex-col gap-4" novalidate @submit.prevent="save">
                <FormAlert v-if="alertMessage" :message="alertMessage" />

                <fieldset :disabled="!canManage" class="contents">
                    <section :class="cardClass" aria-labelledby="coupon-code-title">
                        <h2 id="coupon-code-title" class="text-lg font-bold">
                            {{ t('admin.discounts.coupons.sections.code') }}
                        </h2>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="flex items-start gap-2">
                                <div class="min-w-0 grow">
                                    <TextField
                                        :model-value="fields.code"
                                        :label="t('admin.discounts.coupons.code')"
                                        :hint="t('admin.discounts.coupons.codeHint')"
                                        dir="ltr"
                                        autocomplete="off"
                                        :error="errorOf('code')"
                                        class="font-mono"
                                        @update:model-value="onCodeInput"
                                    />
                                </div>
                                <button
                                    type="button"
                                    :class="[smallButtonClass, 'mt-7.5 h-13 rounded-xl']"
                                    @click="generate"
                                >
                                    <AppIcon name="refresh" :size="16" />
                                    {{ t('admin.discounts.coupons.generate') }}
                                </button>
                            </div>
                            <TextField
                                v-model="fields.name"
                                :label="t('admin.discounts.coupons.name')"
                                :hint="t('admin.discounts.coupons.nameHint')"
                                autocomplete="off"
                                :error="errorOf('name')"
                            />
                        </div>
                    </section>

                    <section :class="cardClass" aria-labelledby="coupon-discount-title">
                        <h2 id="coupon-discount-title" class="text-lg font-bold">
                            {{ t('admin.discounts.coupons.sections.discount') }}
                        </h2>
                        <div class="max-w-xl">
                            <SegmentedControl
                                v-model="fields.type"
                                :label="t('admin.discounts.coupons.sections.discount')"
                                :options="typeOptions"
                            />
                        </div>
                        <div v-if="fields.type === 'percent'" class="grid gap-4 sm:grid-cols-2">
                            <NumberField
                                v-model="fields.value"
                                :label="t('admin.discounts.coupons.percent')"
                                :hint="t('admin.discounts.coupons.percentHint', { max: formatNumber(MAX_PERCENT) })"
                                unit="%"
                                :error="errorOf('value')"
                            />
                            <NumberField
                                v-model="fields.maxDiscount"
                                :label="t('admin.discounts.coupons.maxDiscount')"
                                :hint="t('admin.discounts.coupons.maxDiscountHint')"
                                :unit="money.unit.value"
                                :factor="money.rialsPerUnit.value"
                                optional
                                :error="errorOf('max_discount')"
                            />
                        </div>
                        <div v-else-if="fields.type === 'fixed'" class="grid gap-4 sm:grid-cols-2">
                            <NumberField
                                v-model="fields.value"
                                :label="t('admin.discounts.coupons.amount')"
                                :hint="t('admin.discounts.coupons.amountHint')"
                                :unit="money.unit.value"
                                :factor="money.rialsPerUnit.value"
                                :error="errorOf('value')"
                            />
                        </div>
                        <p v-else class="flex items-start gap-2 text-[13px] text-fg-muted">
                            <AppIcon name="info" :size="16" class="mt-0.5 shrink-0" />
                            {{ t('admin.discounts.coupons.freeShippingHint') }}
                        </p>
                    </section>

                    <section :class="cardClass" aria-labelledby="coupon-applies-title">
                        <h2 id="coupon-applies-title" class="text-lg font-bold">
                            {{ t('admin.discounts.coupons.sections.applies') }}
                        </h2>
                        <div class="max-w-md">
                            <SegmentedControl
                                v-model="fields.appliesTo"
                                :label="t('admin.discounts.coupons.sections.applies')"
                                :options="appliesOptions"
                            />
                        </div>

                        <div
                            v-if="fields.appliesTo === 'selected'"
                            :class="[boxClass, targetsError && 'border-danger']"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm font-semibold">{{ t('admin.discounts.coupons.included') }}</span>
                                <button type="button" :class="smallButtonClass" @click="targetsOpen = true">
                                    <AppIcon name="plus" :size="16" />
                                    {{ t('admin.discounts.coupons.add') }}
                                </button>
                            </div>
                            <ul v-if="included.length > 0" class="flex flex-wrap gap-2">
                                <li
                                    v-for="item in included"
                                    :key="`${item.kind}-${item.link.id}`"
                                    class="inline-flex h-8 items-center gap-1.5 rounded-full bg-primary-soft ps-3 pe-1 text-[13px]"
                                >
                                    <span class="text-[11px] font-semibold text-primary">{{
                                        t(`admin.discounts.coupons.kinds.${item.kind}`)
                                    }}</span>
                                    {{ item.link.name }}
                                    <button
                                        type="button"
                                        :class="chipRemoveClass"
                                        :aria-label="t('admin.discounts.coupons.removeItem', { name: item.link.name })"
                                        @click="removeTarget(item.kind, item.link.id)"
                                    >
                                        <AppIcon name="close" :size="13" />
                                    </button>
                                </li>
                            </ul>
                            <p class="text-xs text-fg-muted">{{ t('admin.discounts.coupons.includedHint') }}</p>
                            <p v-if="targetsError" class="text-[13px] text-danger">{{ targetsError }}</p>
                        </div>

                        <div :class="[boxClass, excludedError && 'border-danger']">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm font-semibold">{{ t('admin.discounts.coupons.excluded') }}</span>
                                <button type="button" :class="smallButtonClass" @click="excludeOpen = true">
                                    <AppIcon name="plus" :size="16" />
                                    {{ t('admin.discounts.coupons.addProduct') }}
                                </button>
                            </div>
                            <ul v-if="fields.excluded.length > 0" class="flex flex-wrap gap-2">
                                <li
                                    v-for="product in fields.excluded"
                                    :key="product.id"
                                    class="inline-flex h-8 items-center gap-1.5 rounded-full bg-danger-soft ps-3 pe-1 text-[13px]"
                                >
                                    {{ product.name }}
                                    <button
                                        type="button"
                                        :class="chipRemoveClass"
                                        :aria-label="t('admin.discounts.coupons.removeItem', { name: product.name })"
                                        @click="
                                            fields.excluded = fields.excluded.filter((item) => item.id !== product.id)
                                        "
                                    >
                                        <AppIcon name="close" :size="13" />
                                    </button>
                                </li>
                            </ul>
                            <p v-else class="text-xs text-fg-muted">{{ t('admin.discounts.coupons.excludedNone') }}</p>
                            <p v-if="excludedError" class="text-[13px] text-danger">{{ excludedError }}</p>
                        </div>

                        <label class="flex cursor-pointer items-start gap-2.5 rounded-xl bg-surface-muted/60 p-3.5">
                            <input
                                v-model="fields.excludeDiscounted"
                                type="checkbox"
                                class="mt-1 size-4 accent-[var(--color-primary)]"
                            />
                            <span class="flex flex-col gap-0.5">
                                <span class="text-sm font-semibold">{{
                                    t('admin.discounts.coupons.excludeDiscounted')
                                }}</span>
                                <span class="text-[13px] text-fg-muted">{{
                                    t('admin.discounts.coupons.excludeDiscountedHint')
                                }}</span>
                            </span>
                        </label>
                    </section>

                    <section :class="cardClass" aria-labelledby="coupon-who-title">
                        <h2 id="coupon-who-title" class="text-lg font-bold">
                            {{ t('admin.discounts.coupons.sections.who') }}
                        </h2>
                        <div class="max-w-md">
                            <SegmentedControl
                                v-model="fields.customerScope"
                                :label="t('admin.discounts.coupons.sections.who')"
                                :options="customerOptions"
                            />
                        </div>
                        <template v-if="fields.customerScope === 'selected'">
                            <div :class="[boxClass, customersError && 'border-danger']">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-semibold">{{
                                        t('admin.discounts.coupons.customerCount', {
                                            n: formatNumber(fields.customers.length),
                                        })
                                    }}</span>
                                    <button type="button" :class="smallButtonClass" @click="customersOpen = true">
                                        <AppIcon name="userPlus" :size="16" />
                                        {{ t('admin.discounts.coupons.addCustomer') }}
                                    </button>
                                </div>
                                <ul v-if="fields.customers.length > 0" class="flex flex-col divide-y divide-line">
                                    <li
                                        v-for="customer in shownCustomers"
                                        :key="customer.id"
                                        class="flex items-center gap-2.5 py-2"
                                    >
                                        <span
                                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-[13px] font-bold text-primary"
                                            aria-hidden="true"
                                            >{{ customer.name.charAt(0) }}</span
                                        >
                                        <span class="flex min-w-0 grow flex-col">
                                            <span class="truncate text-[13px] font-semibold">{{ customer.name }}</span>
                                            <span dir="ltr" class="text-start text-xs text-fg-muted">{{
                                                localizeDigits(customer.phone_number)
                                            }}</span>
                                        </span>
                                        <span class="shrink-0 text-xs text-fg-muted">{{
                                            customer.used_count > 0
                                                ? t('admin.discounts.coupons.customerUsed', {
                                                      n: formatNumber(customer.used_count),
                                                  })
                                                : t('admin.discounts.coupons.customerNotUsed')
                                        }}</span>
                                        <button
                                            type="button"
                                            class="flex size-8 shrink-0 items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                            :aria-label="
                                                t('admin.discounts.coupons.removeItem', { name: customer.name })
                                            "
                                            @click="removeCustomer(customer.id)"
                                        >
                                            <AppIcon name="close" :size="15" />
                                        </button>
                                    </li>
                                </ul>
                                <button
                                    v-if="fields.customers.length > SHOWN_CUSTOMERS"
                                    type="button"
                                    class="w-fit rounded text-[13px] font-semibold text-primary hover:underline focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                    @click="showAllCustomers = !showAllCustomers"
                                >
                                    {{
                                        showAllCustomers
                                            ? t('admin.discounts.coupons.showFewer')
                                            : t('admin.discounts.coupons.showAll', {
                                                  n: formatNumber(fields.customers.length),
                                              })
                                    }}
                                </button>
                                <p v-if="customersError" class="text-[13px] text-danger">{{ customersError }}</p>
                            </div>
                            <p class="text-xs leading-relaxed text-fg-muted">
                                {{ t('admin.discounts.coupons.whoHint') }}
                            </p>
                        </template>
                    </section>

                    <section :class="cardClass" aria-labelledby="coupon-limits-title">
                        <h2 id="coupon-limits-title" class="text-lg font-bold">
                            {{ t('admin.discounts.coupons.sections.limits') }}
                        </h2>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <NumberField
                                v-model="fields.minOrderAmount"
                                :label="t('admin.discounts.coupons.minOrder')"
                                :hint="t('admin.discounts.coupons.minOrderHint')"
                                :unit="money.unit.value"
                                :factor="money.rialsPerUnit.value"
                                optional
                                :error="errorOf('min_order_amount')"
                            />
                            <NumberField
                                v-model="fields.usageLimit"
                                :label="t('admin.discounts.coupons.usageLimit')"
                                :hint="
                                    coupon
                                        ? t('admin.discounts.coupons.usageLimitHintUsed', {
                                              n: formatNumber(coupon.used_count),
                                          })
                                        : t('admin.discounts.coupons.unlimitedHint')
                                "
                                :unit="t('admin.discounts.coupons.times')"
                                :min="1"
                                optional
                                :error="errorOf('usage_limit')"
                            />
                            <NumberField
                                v-model="fields.perCustomerLimit"
                                :label="t('admin.discounts.coupons.perCustomer')"
                                :hint="t('admin.discounts.coupons.unlimitedHint')"
                                :unit="t('admin.discounts.coupons.times')"
                                :min="1"
                                optional
                                :error="errorOf('per_customer_limit')"
                            />
                        </div>
                        <label class="flex w-fit cursor-pointer items-center gap-2.5">
                            <input
                                v-model="fields.firstOrderOnly"
                                type="checkbox"
                                class="size-4 accent-[var(--color-primary)]"
                            />
                            <span class="text-sm font-semibold">{{ t('admin.discounts.coupons.firstOrderOnly') }}</span>
                        </label>
                    </section>

                    <section :class="cardClass" aria-labelledby="coupon-schedule-title">
                        <h2 id="coupon-schedule-title" class="text-lg font-bold">
                            {{ t('admin.discounts.coupons.sections.schedule') }}
                        </h2>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="coupon-starts" class="mb-2 block text-sm font-semibold">{{
                                    t('admin.discounts.coupons.startsAt')
                                }}</label>
                                <input
                                    id="coupon-starts"
                                    v-model="fields.startsAt"
                                    type="datetime-local"
                                    :class="[dateClass, errorOf('starts_at') && 'border-2 border-danger']"
                                />
                                <p v-if="errorOf('starts_at')" class="mt-2 text-[13px] text-danger">
                                    {{ errorOf('starts_at') }}
                                </p>
                                <p v-else class="mt-2 text-[13px] text-fg-muted">
                                    {{ t('admin.discounts.coupons.startsAtHint') }}
                                </p>
                            </div>
                            <div>
                                <label for="coupon-ends" class="mb-2 block text-sm font-semibold"
                                    >{{ t('admin.discounts.coupons.endsAt') }}
                                    <span class="font-normal text-fg-muted">{{ t('common.optional') }}</span></label
                                >
                                <input
                                    id="coupon-ends"
                                    v-model="fields.endsAt"
                                    type="datetime-local"
                                    :min="fields.startsAt || undefined"
                                    :class="[dateClass, errorOf('ends_at') && 'border-2 border-danger']"
                                />
                                <p v-if="errorOf('ends_at')" class="mt-2 text-[13px] text-danger">
                                    {{ errorOf('ends_at') }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-surface-muted/60 px-3.5 py-3"
                        >
                            <span class="text-[13px] text-fg-muted" aria-live="polite">{{ scheduleNote }}</span>
                            <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold">
                                <input
                                    v-model="fields.isActive"
                                    type="checkbox"
                                    class="size-4 accent-[var(--color-primary)]"
                                />
                                {{ t('admin.discounts.coupons.active') }}
                            </label>
                        </div>
                    </section>
                </fieldset>
            </form>

            <aside class="flex flex-col gap-4 lg:sticky lg:top-4">
                <CouponSampleOrder v-if="canManage" :coupon="payload()" :valid="valid" />

                <section
                    v-if="coupon"
                    class="flex flex-col gap-2.5 rounded-2xl border border-line bg-surface p-5"
                    aria-labelledby="coupon-usage-title"
                >
                    <h2 id="coupon-usage-title" class="text-base font-bold">
                        {{ t('admin.discounts.coupons.usage.title') }}
                    </h2>
                    <div class="flex justify-between gap-3 text-[13px]">
                        <span>{{
                            coupon.usage_limit === null
                                ? t('admin.discounts.coupons.list.usedUnlimited', {
                                      n: formatNumber(coupon.used_count),
                                  })
                                : t('admin.discounts.coupons.list.usedOf', {
                                      n: formatNumber(coupon.used_count),
                                      limit: formatNumber(coupon.usage_limit),
                                  })
                        }}</span>
                        <span v-if="usageLeft !== null" class="text-fg-muted">{{
                            t('admin.discounts.coupons.usage.left', { n: formatNumber(usageLeft) })
                        }}</span>
                    </div>
                    <span class="h-2 overflow-hidden rounded-full bg-surface-muted" aria-hidden="true">
                        <span
                            :class="[
                                'block h-full rounded-full',
                                coupon.state === 'used_up' ? 'bg-danger' : 'bg-primary',
                                coupon.usage_limit === null && 'opacity-25',
                            ]"
                            :style="{ width: `${usagePercent(coupon.used_count, coupon.usage_limit) ?? 100}%` }"
                        />
                    </span>
                    <p class="text-xs leading-relaxed text-fg-muted">{{ t('admin.discounts.coupons.usage.orders') }}</p>
                </section>
            </aside>
        </div>

        <div
            v-if="!loading && !loadError && canManage"
            class="sticky bottom-0 z-30 -mx-4 flex flex-wrap items-center gap-3 border-t border-line bg-surface/95 px-4 py-3 shadow-[0_-4px_16px_rgb(0_0_0/0.06)] backdrop-blur sm:mx-0 sm:rounded-t-2xl sm:border-x"
        >
            <span class="grow" />
            <span v-if="dirty" class="text-xs text-warning">{{ t('admin.discounts.unsaved') }}</span>
            <AppButton type="submit" form="coupon-form" size="md" :loading="form.processing.value">
                {{ coupon ? t('admin.discounts.coupons.save') : t('admin.discounts.coupons.create') }}
            </AppButton>
        </div>

        <CouponTargetsDialog
            :open="targetsOpen"
            :targets="fields.targets"
            @close="targetsOpen = false"
            @confirm="setTargets"
        />
        <ProductPickerDialog
            :open="excludeOpen"
            mode="choose"
            :title="t('admin.discounts.coupons.excludeTitle')"
            :intro="t('admin.discounts.coupons.excludeIntro')"
            :initial="fields.excluded"
            @close="excludeOpen = false"
            @confirm="setExcluded"
        />
        <CouponCustomersDialog
            :open="customersOpen"
            :chosen-ids="chosenCustomerIds"
            @close="customersOpen = false"
            @add="addCustomers"
        />

        <AppDialog
            :open="confirmDelete"
            :title="t('admin.discounts.coupons.deleteTitle', { code: coupon?.code ?? '' })"
            icon="trash"
            tone="danger"
            :busy="deleting"
            @close="confirmDelete = false"
        >
            <p class="text-sm leading-loose">{{ t('admin.discounts.coupons.deleteText') }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" :loading="deleting" @click="remove">{{
                    t('admin.discounts.coupons.deleteConfirm')
                }}</AppButton>
                <AppButton variant="secondary" size="md" :disabled="deleting" @click="confirmDelete = false">{{
                    t('common.cancel')
                }}</AppButton>
            </template>
        </AppDialog>

        <AppDialog
            :open="leaveTarget !== null"
            :title="t('admin.discounts.leaveTitle')"
            icon="alert"
            tone="danger"
            @close="stay"
        >
            <p class="text-sm leading-loose">{{ t('admin.discounts.coupons.leaveText') }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" @click="leave">{{ t('admin.discounts.leaveConfirm') }}</AppButton>
                <AppButton variant="secondary" size="md" @click="stay">{{ t('admin.discounts.leaveStay') }}</AppButton>
            </template>
        </AppDialog>
    </div>
</template>
