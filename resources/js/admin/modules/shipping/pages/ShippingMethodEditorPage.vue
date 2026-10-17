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
import TextAreaField from '@/shared/ui/TextAreaField.vue';
import TextField from '@/shared/ui/TextField.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { useMoney, useSettingsStore } from '../../settings/store';
import { shippingApi } from '../api';
import CourierCitiesDialog from '../components/CourierCitiesDialog.vue';
import RatesEditor from '../components/RatesEditor.vue';
import ShippingTestBox from '../components/ShippingTestBox.vue';
import {
    changeType,
    emptyForm,
    formFromMethod,
    MAX_CITIES,
    mergeCities,
    type ShippingForm,
    shippingProblems,
    toShippingPayload,
} from '../shipping';
import type { SaveShippingMethodPayload, ShippingCity, ShippingMethod, ShippingType } from '../types';

/**
 * Create (`id` null; `copyFrom` starts from another method, `initialType` / `initialName` come
 * from the "New method" dialog) or edit a shipping method: its name and delivery time, how its
 * fee is worked out (weight ranges, volume ranges or courier cities), who pays the fee and when,
 * its conditions and whether it is on. The test box beside the form prices the unsaved method.
 */
const props = defineProps<{
    id: number | null;
    copyFrom: number | null;
    initialType: ShippingType | null;
    initialName: string | null;
}>();

const { t } = useI18n();
const { formatNumber } = useLocale();
const money = useMoney();
const settings = useSettingsStore();
const toast = useToast();
const router = useRouter();
const auth = useAdminAuthStore();
const canManage = computed(() => auth.can(Permission.ManageShipping));

const FIELDS = [
    'name',
    'description',
    'type',
    'fee_on_delivery',
    'default_fee',
    'packaging_weight_grams',
    'min_order_amount',
    'free_over_amount',
    'delivery_days_min',
    'delivery_days_max',
    'is_active',
    'rates',
    'cities',
] as const;
const form = useApiForm(FIELDS);

const method = ref<ShippingMethod | null>(null);
const position = ref<{ n: number; of: number } | null>(null);
const loading = ref(true);
const loadError = ref<string | null>(null);
const fields = ref<ShippingForm>(emptyForm());

const payload = (): SaveShippingMethodPayload => toShippingPayload(fields.value);
const snapshot = ref('');

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;

    try {
        if (props.id !== null) {
            const [loaded, list] = await Promise.all([shippingApi.show(props.id), shippingApi.list()]);
            method.value = loaded;
            fields.value = formFromMethod(loaded);
            snapshot.value = JSON.stringify(payload());
            const index = list.data.findIndex((item) => item.id === loaded.id);
            position.value = index === -1 ? null : { n: index + 1, of: list.data.length };
        } else {
            const start = emptyForm(props.initialType ?? 'weight');
            snapshot.value = JSON.stringify(toShippingPayload(start));

            if (props.copyFrom !== null) {
                const source = await shippingApi.show(props.copyFrom);
                fields.value = {
                    ...formFromMethod(source),
                    name: t('admin.shipping.copyName', { name: source.name }),
                    isActive: false,
                };
            } else {
                fields.value = { ...start, name: props.initialName ?? '' };
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
    (['weight', 'volume', 'courier'] as const).map((value) => ({ value, label: t(`admin.shipping.types.${value}`) })),
);
const selectedType = computed({
    get: () => fields.value.type,
    set: (type: ShippingType) => {
        fields.value = changeType(fields.value, type);
    },
});
const rangeType = computed(() => (fields.value.type === 'volume' ? 'volume' : 'weight'));

const citiesOpen = ref(false);
const chosenCityIds = computed(() => new Set(fields.value.cities.map((city) => city.id)));

function addCities(cities: ShippingCity[]): void {
    fields.value.cities = mergeCities(fields.value.cities, cities);
    citiesOpen.value = false;
}

function removeCity(id: number): void {
    fields.value.cities = fields.value.cities.filter((city) => city.id !== id);
}

function setCityFee(id: number, fee: number | null): void {
    fields.value.cities = fields.value.cities.map((city) => (city.id === id ? { ...city, fee } : city));
}

// ---- checks and errors ----

const attempted = ref(false);
const problems = computed(() => shippingProblems(fields.value));
const valid = computed(() => Object.keys(problems.value).length === 0);

const fieldErrors = computed(() => form.lastError.value?.fieldErrors ?? {});

/** The server's error for a key (`rates.2.end`, `cities.0.id`, ...), else the client check once a save was tried. */
function errorOf(key: string): string | undefined {
    const server = fieldErrors.value[key]?.[0];

    if (server) {
        return server;
    }

    const problem = problems.value[key];

    return attempted.value && problem
        ? t(`admin.shipping.problems.${problem}`, { max: formatNumber(MAX_CITIES) })
        : undefined;
}

const rateErrors = computed(() => {
    const errors: Record<string, string | undefined> = {};
    const keys = new Set([...Object.keys(fieldErrors.value), ...Object.keys(problems.value)]);

    for (const key of keys) {
        if (key === 'rates' || key.startsWith('rates.')) {
            errors[key] = errorOf(key);
        }
    }

    return errors;
});

const citiesError = computed(
    () => errorOf('cities') ?? Object.entries(fieldErrors.value).find(([key]) => key.startsWith('cities.'))?.[1]?.[0],
);

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
        toast.show(t('admin.shipping.fixProblems'), 'danger');

        return;
    }

    const existing = method.value;
    const body = payload();
    const saved = await form.submit(() =>
        existing ? shippingApi.update(existing.id, body) : shippingApi.create(body),
    );

    if (!saved) {
        toast.show(t('admin.shipping.saveFailed'), 'danger');

        return;
    }

    method.value = saved;
    fields.value = formFromMethod(saved);
    snapshot.value = JSON.stringify(payload());
    attempted.value = false;
    toast.show(t(existing ? 'admin.shipping.saved' : 'admin.shipping.created', { name: saved.name }));

    if (!existing) {
        await router.replace({ name: 'shipping-method', params: { id: saved.id } });
    }
}

const confirmDelete = ref(false);
const deleting = ref(false);

async function remove(): Promise<void> {
    if (!method.value) {
        return;
    }

    deleting.value = true;

    try {
        await shippingApi.remove(method.value.id);
    } catch (caught) {
        toast.show(toApiError(caught).message, 'danger');
        deleting.value = false;

        return;
    }

    snapshot.value = JSON.stringify(payload());
    toast.show(t('admin.shipping.list.deleted', { name: method.value.name }));
    await router.push({ name: 'shipping-methods' });
}

// ---- leaving with unsaved changes ----

const leaveTarget = ref<null | (() => void)>(null);
let leaveCancel: (() => void) | null = null;

onBeforeRouteLeave(
    (to) =>
        new Promise<boolean>((resolve) => {
            if (!dirty.value || (to.name === 'shipping-method' && method.value !== null)) {
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

const headerDetail = computed(() => {
    const parts: string[] = [];

    if (position.value) {
        parts.push(
            t('admin.shipping.positionOf', { n: formatNumber(position.value.n), of: formatNumber(position.value.of) }),
        );
    }

    if (method.value?.type === 'courier') {
        parts.push(t('admin.shipping.list.cityCount', { n: formatNumber(method.value.cities.length) }));
    }

    return parts.join(' · ');
});

const typeIntro = computed(() => t(`admin.shipping.pricing.intro.${fields.value.type}`));

const defaultWeightText = computed(() => {
    const grams = settings.settings.default_weight_grams;

    return grams === null
        ? t('admin.shipping.pricing.noDefaultWeight')
        : t('admin.shipping.pricing.defaultWeight', { grams: formatNumber(grams) });
});

const sortedCities = computed(() =>
    [...fields.value.cities].sort(
        (a, b) => a.province.name.localeCompare(b.province.name, 'fa') || a.name.localeCompare(b.name, 'fa'),
    ),
);

const cardClass = 'flex flex-col gap-4.5 rounded-2xl border border-line bg-surface p-5 sm:p-6';
const smallButtonClass =
    'flex h-9 shrink-0 items-center gap-1.5 rounded-[10px] border border-line-strong px-3 text-[13px] font-semibold hover:bg-surface-muted focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none disabled:opacity-50';
const choiceClass = (on: boolean): string[] => [
    'flex cursor-pointer gap-2.5 rounded-xl p-3.5 focus-within:ring-2 focus-within:ring-primary',
    on ? 'border-2 border-primary bg-primary-soft/40' : 'border border-line',
];
</script>

<template>
    <div class="mx-auto flex max-w-7xl flex-col gap-5">
        <RouterLink
            :to="{ name: 'shipping-methods' }"
            class="flex w-fit items-center gap-1.5 rounded-lg text-sm font-medium text-fg-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
        >
            <AppIcon name="arrowForward" :size="16" flip-in-rtl class="rotate-180" />
            {{ t('admin.shipping.backToList') }}
        </RouterLink>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 flex-col gap-1">
                <div class="flex min-w-0 flex-wrap items-center gap-2.5">
                    <h1 class="truncate text-2xl font-bold">
                        {{ method ? method.name : t('admin.shipping.newTitle') }}
                    </h1>
                    <AppBadge v-if="method" tone="neutral">{{ t(`admin.shipping.types.${method.type}`) }}</AppBadge>
                    <AppBadge v-if="method?.fee_on_delivery" tone="warning">{{
                        t('admin.shipping.payment.onDelivery')
                    }}</AppBadge>
                    <AppBadge v-if="method" :tone="method.is_active ? 'success' : 'neutral'">{{
                        method.is_active ? t('admin.shipping.list.active') : t('admin.shipping.list.inactive')
                    }}</AppBadge>
                </div>
                <p v-if="headerDetail" class="text-xs text-fg-muted">{{ headerDetail }}</p>
            </div>
            <AppButton v-if="method && canManage" variant="danger-outline" size="md" @click="confirmDelete = true">
                <AppIcon name="trash" :size="18" />
                {{ t('admin.shipping.list.delete') }}
            </AppButton>
        </div>

        <div v-if="loading" class="flex justify-center py-16"><AppSpinner /></div>
        <div v-else-if="loadError" class="flex flex-col gap-3">
            <FormAlert :message="loadError" />
            <div>
                <AppButton variant="secondary" size="md" @click="load">{{ t('common.retry') }}</AppButton>
            </div>
        </div>

        <div v-else class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_24rem]">
            <form id="shipping-form" class="flex min-w-0 flex-col gap-4" novalidate @submit.prevent="save">
                <FormAlert v-if="alertMessage" :message="alertMessage" />

                <fieldset :disabled="!canManage" class="contents">
                    <section :class="cardClass" aria-labelledby="shipping-general-title">
                        <h2 id="shipping-general-title" class="text-lg font-bold">
                            {{ t('admin.shipping.sections.general') }}
                        </h2>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <TextField
                                v-model="fields.name"
                                :label="t('admin.shipping.fields.name')"
                                :hint="t('admin.shipping.fields.nameHint')"
                                autocomplete="off"
                                :error="errorOf('name')"
                            />
                            <div class="grid grid-cols-2 gap-3">
                                <NumberField
                                    v-model="fields.daysMin"
                                    :label="t('admin.shipping.fields.daysMin')"
                                    :unit="t('admin.shipping.units.days')"
                                    optional
                                    :error="errorOf('delivery_days_min')"
                                />
                                <NumberField
                                    v-model="fields.daysMax"
                                    :label="t('admin.shipping.fields.daysMax')"
                                    :unit="t('admin.shipping.units.days')"
                                    optional
                                    :error="errorOf('delivery_days_max')"
                                />
                                <p class="col-span-2 -mt-2 text-xs text-fg-muted">
                                    {{ t('admin.shipping.fields.daysHint') }}
                                </p>
                            </div>
                        </div>
                        <TextAreaField
                            v-model="fields.description"
                            :label="t('admin.shipping.fields.description')"
                            :hint="t('admin.shipping.fields.descriptionHint')"
                            :rows="2"
                            :maxlength="500"
                            optional
                            :error="errorOf('description')"
                        />
                    </section>

                    <section :class="cardClass" aria-labelledby="shipping-pricing-title">
                        <h2 id="shipping-pricing-title" class="text-lg font-bold">
                            {{ t('admin.shipping.sections.pricing') }}
                        </h2>
                        <div class="max-w-xl">
                            <SegmentedControl
                                v-model="selectedType"
                                :label="t('admin.shipping.sections.pricing')"
                                :options="typeOptions"
                            />
                        </div>
                        <p class="text-[13px] leading-relaxed text-fg-muted">{{ typeIntro }}</p>

                        <template v-if="fields.type !== 'courier'">
                            <RatesEditor v-model="fields.rates" :type="rangeType" :errors="rateErrors" />
                            <div class="grid gap-4 sm:grid-cols-2">
                                <NumberField
                                    v-if="fields.type === 'weight'"
                                    v-model="fields.packagingWeightGrams"
                                    :label="t('admin.shipping.fields.packaging')"
                                    :hint="t('admin.shipping.fields.packagingHint')"
                                    :unit="t('admin.shipping.units.grams')"
                                    optional
                                    :error="errorOf('packaging_weight_grams')"
                                />
                                <div class="flex flex-col gap-1.5 self-start rounded-xl bg-surface-muted/60 p-3.5">
                                    <span class="text-sm font-semibold">{{
                                        fields.type === 'weight'
                                            ? t('admin.shipping.pricing.missingWeightTitle')
                                            : t('admin.shipping.pricing.missingDimensionsTitle')
                                    }}</span>
                                    <span class="text-xs leading-relaxed text-fg-muted">{{
                                        fields.type === 'weight'
                                            ? defaultWeightText
                                            : t('admin.shipping.pricing.missingDimensions')
                                    }}</span>
                                    <span class="flex flex-wrap gap-x-4 gap-y-1 text-xs font-semibold">
                                        <RouterLink
                                            v-if="fields.type === 'weight'"
                                            :to="{ name: 'settings' }"
                                            class="rounded text-primary hover:underline focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                            >{{ t('admin.shipping.pricing.changeDefault') }}</RouterLink
                                        >
                                        <RouterLink
                                            :to="{
                                                name: 'products',
                                                query: {
                                                    shipping:
                                                        fields.type === 'weight'
                                                            ? 'missing_weight'
                                                            : 'missing_dimensions',
                                                },
                                            }"
                                            class="rounded text-primary hover:underline focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                            >{{ t('admin.shipping.pricing.showProducts') }}</RouterLink
                                        >
                                    </span>
                                </div>
                            </div>
                        </template>

                        <template v-else>
                            <div class="max-w-xs">
                                <NumberField
                                    v-model="fields.defaultFee"
                                    :label="t('admin.shipping.fields.defaultFee')"
                                    :hint="t('admin.shipping.fields.defaultFeeHint')"
                                    :unit="money.unit.value"
                                    :factor="money.rialsPerUnit.value"
                                    :error="errorOf('default_fee')"
                                />
                            </div>
                            <div
                                :class="[
                                    'overflow-hidden rounded-xl border',
                                    citiesError ? 'border-danger' : 'border-line',
                                ]"
                            >
                                <div class="flex items-center justify-between gap-3 px-3.5 py-2.5">
                                    <span class="text-sm font-semibold">{{
                                        t('admin.shipping.cities.title', { n: formatNumber(fields.cities.length) })
                                    }}</span>
                                    <button type="button" :class="smallButtonClass" @click="citiesOpen = true">
                                        <AppIcon name="plus" :size="16" />
                                        {{ t('admin.shipping.cities.addButton') }}
                                    </button>
                                </div>
                                <p
                                    v-if="fields.cities.length === 0"
                                    class="border-t border-line px-3.5 py-6 text-center text-[13px] text-fg-muted"
                                >
                                    {{ t('admin.shipping.cities.empty') }}
                                </p>
                                <template v-else>
                                    <div
                                        class="hidden grid-cols-[minmax(0,1fr)_9rem_14rem_2.75rem] gap-3 border-t border-line bg-surface-muted/70 px-3.5 py-2.5 text-xs font-semibold text-fg-muted sm:grid"
                                    >
                                        <span>{{ t('admin.shipping.cities.city') }}</span>
                                        <span>{{ t('admin.shipping.cities.province') }}</span>
                                        <span>{{ t('admin.shipping.cities.fee') }}</span>
                                        <span />
                                    </div>
                                    <ul class="flex flex-col divide-y divide-line border-t border-line">
                                        <li
                                            v-for="city in sortedCities"
                                            :key="city.id"
                                            class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-2 px-3.5 py-2 sm:grid-cols-[minmax(0,1fr)_9rem_14rem_2.75rem]"
                                        >
                                            <span class="text-[13px] font-semibold">{{ city.name }}</span>
                                            <span class="text-[13px] text-fg-muted">{{ city.province.name }}</span>
                                            <NumberField
                                                :model-value="city.fee"
                                                :label="t('admin.shipping.cities.feeFor', { name: city.name })"
                                                :unit="money.unit.value"
                                                :factor="money.rialsPerUnit.value"
                                                :placeholder="
                                                    fields.defaultFee === null
                                                        ? t('admin.shipping.cities.defaultFee')
                                                        : money.format(fields.defaultFee)
                                                "
                                                compact
                                                @update:model-value="setCityFee(city.id, $event)"
                                            />
                                            <button
                                                type="button"
                                                class="flex size-10 items-center justify-center justify-self-end rounded-lg text-fg-muted hover:bg-surface-muted hover:text-danger focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                                :aria-label="t('admin.shipping.cities.remove', { name: city.name })"
                                                @click="removeCity(city.id)"
                                            >
                                                <AppIcon name="close" :size="16" />
                                            </button>
                                        </li>
                                    </ul>
                                </template>
                            </div>
                            <p v-if="citiesError" class="text-[13px] text-danger">{{ citiesError }}</p>
                            <p class="text-xs leading-relaxed text-fg-muted">{{ t('admin.shipping.cities.hint') }}</p>
                        </template>
                    </section>

                    <section :class="cardClass" aria-labelledby="shipping-payment-title">
                        <h2 id="shipping-payment-title" class="text-lg font-bold">
                            {{ t('admin.shipping.sections.payment') }}
                        </h2>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label :class="choiceClass(!fields.feeOnDelivery)">
                                <input
                                    v-model="fields.feeOnDelivery"
                                    type="radio"
                                    name="fee-payment"
                                    :value="false"
                                    class="mt-0.5 size-4.5 shrink-0 accent-[var(--color-primary)]"
                                />
                                <span class="flex flex-col gap-0.5">
                                    <span class="text-sm font-semibold">{{
                                        t('admin.shipping.payment.withOrder')
                                    }}</span>
                                    <span class="text-xs leading-relaxed text-fg-muted">{{
                                        t('admin.shipping.payment.withOrderHint')
                                    }}</span>
                                </span>
                            </label>
                            <label :class="choiceClass(fields.feeOnDelivery)">
                                <input
                                    v-model="fields.feeOnDelivery"
                                    type="radio"
                                    name="fee-payment"
                                    :value="true"
                                    class="mt-0.5 size-4.5 shrink-0 accent-[var(--color-primary)]"
                                />
                                <span class="flex flex-col gap-0.5">
                                    <span class="text-sm font-semibold">{{
                                        t('admin.shipping.payment.onDelivery')
                                    }}</span>
                                    <span class="text-xs leading-relaxed text-fg-muted">{{
                                        t('admin.shipping.payment.onDeliveryHint')
                                    }}</span>
                                </span>
                            </label>
                        </div>
                        <p class="text-xs leading-relaxed text-fg-muted">{{ t('admin.shipping.payment.note') }}</p>
                    </section>

                    <section :class="cardClass" aria-labelledby="shipping-conditions-title">
                        <h2 id="shipping-conditions-title" class="text-lg font-bold">
                            {{ t('admin.shipping.sections.conditions') }}
                        </h2>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <NumberField
                                v-model="fields.minOrderAmount"
                                :label="t('admin.shipping.fields.minOrder')"
                                :hint="t('admin.shipping.fields.minOrderHint')"
                                :unit="money.unit.value"
                                :factor="money.rialsPerUnit.value"
                                optional
                                :error="errorOf('min_order_amount')"
                            />
                            <NumberField
                                v-model="fields.freeOverAmount"
                                :label="t('admin.shipping.fields.freeOver')"
                                :hint="t('admin.shipping.fields.freeOverHint')"
                                :unit="money.unit.value"
                                :factor="money.rialsPerUnit.value"
                                optional
                                :error="errorOf('free_over_amount')"
                            />
                        </div>
                        <p class="rounded-xl bg-surface-muted/60 p-3.5 text-xs leading-relaxed text-fg-muted">
                            {{ t('admin.shipping.fields.conditionsNote') }}
                        </p>
                    </section>

                    <section
                        :class="[cardClass, 'sm:flex-row sm:items-center sm:justify-between']"
                        aria-labelledby="shipping-status-title"
                    >
                        <div class="flex flex-col gap-1">
                            <h2 id="shipping-status-title" class="text-lg font-bold">
                                {{ t('admin.shipping.sections.status') }}
                            </h2>
                            <p class="text-[13px] text-fg-muted">{{ t('admin.shipping.fields.activeHint') }}</p>
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold">
                            <input
                                v-model="fields.isActive"
                                type="checkbox"
                                role="switch"
                                class="size-4.5 accent-[var(--color-primary)]"
                            />
                            {{ t('admin.shipping.list.active') }}
                        </label>
                    </section>
                </fieldset>
            </form>

            <aside class="flex flex-col gap-4 lg:sticky lg:top-4">
                <ShippingTestBox v-if="canManage" :method="payload()" :valid="valid" />
            </aside>
        </div>

        <div
            v-if="!loading && !loadError && canManage"
            class="sticky bottom-0 z-30 -mx-4 flex flex-wrap items-center gap-3 border-t border-line bg-surface/95 px-4 py-3 shadow-[0_-4px_16px_rgb(0_0_0/0.06)] backdrop-blur sm:mx-0 sm:rounded-t-2xl sm:border-x"
        >
            <span class="grow" />
            <span v-if="dirty" class="text-xs text-warning">{{ t('admin.shipping.unsaved') }}</span>
            <AppButton type="submit" form="shipping-form" size="md" :loading="form.processing.value">
                {{ method ? t('admin.shipping.save') : t('admin.shipping.create') }}
            </AppButton>
        </div>

        <CourierCitiesDialog
            :open="citiesOpen"
            :chosen-ids="chosenCityIds"
            @close="citiesOpen = false"
            @add="addCities"
        />

        <AppDialog
            :open="confirmDelete"
            :title="t('admin.shipping.deleteTitle', { name: method?.name ?? '' })"
            icon="trash"
            tone="danger"
            :busy="deleting"
            @close="confirmDelete = false"
        >
            <p class="text-sm leading-loose">{{ t('admin.shipping.deleteText') }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" :loading="deleting" @click="remove">{{
                    t('admin.shipping.deleteConfirm')
                }}</AppButton>
                <AppButton variant="secondary" size="md" :disabled="deleting" @click="confirmDelete = false">{{
                    t('common.cancel')
                }}</AppButton>
            </template>
        </AppDialog>

        <AppDialog
            :open="leaveTarget !== null"
            :title="t('admin.shipping.leaveTitle')"
            icon="alert"
            tone="danger"
            @close="stay"
        >
            <p class="text-sm leading-loose">{{ t('admin.shipping.leaveText') }}</p>
            <template #footer>
                <AppButton variant="danger" size="md" @click="leave">{{ t('admin.shipping.leaveConfirm') }}</AppButton>
                <AppButton variant="secondary" size="md" @click="stay">{{ t('admin.shipping.leaveStay') }}</AppButton>
            </template>
        </AppDialog>
    </div>
</template>
