<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { toApiError } from '@/shared/http/errors';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SelectField from '@/shared/ui/SelectField.vue';
import { saleEventsApi } from '../../discounts/api';
import type { SaleEventVariant } from '../../discounts/types';
import ProductPickerDialog, { type PickedProduct } from '../../products/components/ProductPickerDialog.vue';
import { useMoney } from '../../settings/store';
import { shippingApi } from '../api';
import { MEASURE_FACTOR } from '../shipping';
import type { Province, SaveShippingMethodPayload, ShippingSimulation } from '../types';

/**
 * "Test the fee" beside the shipping form: the unsaved method priced by the server for sample
 * products, a destination city and an optional coupon code, with the rules checkout uses. Below
 * it, how a storefront could show the option. Nothing is saved.
 */
const props = defineProps<{ method: SaveShippingMethodPayload; valid: boolean }>();

/** Most products the sample cart holds. */
const MAX_LINES = 8;

const { t } = useI18n();
const { formatNumber } = useLocale();
const money = useMoney();

// ---- sample cart ----

interface Line {
    productId: number;
    productName: string;
    image: string | null;
    variants: SaleEventVariant['variant'][];
    variantId: number;
    quantity: number;
}

const lines = ref<Line[]>([]);
const picking = ref(false);
const adding = ref(false);
const addError = ref<string | null>(null);

async function choose(products: PickedProduct[]): Promise<void> {
    adding.value = true;
    addError.value = null;

    try {
        const kept = lines.value.filter((line) => products.some((product) => product.id === line.productId));
        const newIds = products
            .map((product) => product.id)
            .filter((id) => !kept.some((line) => line.productId === id))
            .slice(0, MAX_LINES - kept.length);
        const variants = newIds.length > 0 ? await saleEventsApi.variants(newIds) : [];
        const added = newIds.flatMap((id): Line[] => {
            const own = variants.filter((item) => item.product.id === id);
            const first = own[0];

            return first
                ? [
                      {
                          productId: id,
                          productName: first.product.name,
                          image: first.product.image,
                          variants: own.map((item) => item.variant),
                          variantId: first.variant.id,
                          quantity: 1,
                      },
                  ]
                : [];
        });
        lines.value = [...kept, ...added];
        picking.value = false;
    } catch (caught) {
        addError.value = toApiError(caught).message;
    } finally {
        adding.value = false;
    }
}

function setQuantity(line: Line, quantity: number): void {
    line.quantity = Math.min(99, Math.max(1, quantity));
}

function remove(line: Line): void {
    lines.value = lines.value.filter((other) => other !== line);
}

const variantOptions = (line: Line): { value: string; label: string }[] =>
    line.variants.map((variant) => ({ value: String(variant.id), label: variant.label || variant.sku || '' }));

// ---- destination and coupon ----

const provinces = ref<Province[]>([]);
const cityId = ref('');
const couponCode = ref('');

void shippingApi
    .provinces()
    .then((loaded) => {
        provinces.value = loaded;
    })
    .catch(() => undefined);

const cityOptions = computed(() => [
    { value: '', label: t('admin.shipping.test.noCity') },
    ...provinces.value.flatMap((province) =>
        province.cities.map((city) => ({ value: String(city.id), label: `${city.name} · ${province.name}` })),
    ),
]);

// A courier is tried in one of its own cities until another is chosen.
watch(
    () => props.method.cities?.[0]?.id,
    (first) => {
        if (
            first !== undefined &&
            (cityId.value === '' || !props.method.cities?.some((city) => String(city.id) === cityId.value))
        ) {
            cityId.value = String(first);
        }
    },
    { immediate: true },
);

// ---- simulation ----

const simulation = ref<ShippingSimulation | null>(null);
const simulating = ref(false);
const error = ref<string | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;
let latest = 0;

async function simulate(): Promise<void> {
    if (!props.valid || lines.value.length === 0) {
        simulation.value = null;

        return;
    }

    const request = ++latest;
    simulating.value = true;
    error.value = null;

    try {
        const result = await shippingApi.simulate({
            method: props.method,
            items: lines.value.map((line) => ({ variant_id: line.variantId, quantity: line.quantity })),
            city_id: cityId.value === '' ? null : Number(cityId.value),
            coupon_code: couponCode.value.trim() || null,
        });

        if (request === latest) {
            simulation.value = result;
        }
    } catch (caught) {
        if (request === latest) {
            error.value = toApiError(caught).message;
            simulation.value = null;
        }
    } finally {
        if (request === latest) {
            simulating.value = false;
        }
    }
}

watch(
    () =>
        JSON.stringify([
            props.method,
            props.valid,
            cityId.value,
            couponCode.value,
            lines.value.map((line) => [line.variantId, line.quantity]),
        ]),
    () => {
        clearTimeout(timer);
        timer = setTimeout(simulate, 500);
    },
);
onBeforeUnmount(() => clearTimeout(timer));

// ---- display ----

const lineResult = (line: Line): ShippingSimulation['lines'][number] | undefined =>
    simulation.value?.lines.find((result) => result.variant_id === line.variantId);

function lineNote(line: Line): { text: string; warn: boolean } | null {
    const result = lineResult(line);

    if (!result) {
        return null;
    }

    if (props.method.type === 'volume') {
        return result.unit_volume_cm3 === null
            ? { text: t('admin.shipping.test.noDimensions'), warn: true }
            : { text: litres(result.unit_volume_cm3 * result.quantity), warn: false };
    }

    if (props.method.type === 'weight') {
        if (result.unit_weight_grams === null) {
            return { text: t('admin.shipping.test.noWeight'), warn: true };
        }

        const total = kilos(result.unit_weight_grams * result.quantity);

        return result.uses_default_weight
            ? { text: t('admin.shipping.test.defaultWeight', { weight: total }), warn: true }
            : { text: total, warn: false };
    }

    return null;
}

const kilos = (grams: number): string => `${formatNumber(grams / MEASURE_FACTOR)} ${t('admin.shipping.units.kg')}`;
const litres = (cm3: number): string => `${formatNumber(cm3 / MEASURE_FACTOR)} ${t('admin.shipping.units.litres')}`;
const measureText = (value: number): string => (props.method.type === 'weight' ? kilos(value) : litres(value));

const shipping = computed(() => simulation.value?.shipping ?? null);

const rangeText = computed(() => {
    const range = shipping.value?.range;

    if (!range) {
        return null;
    }

    return range.end === null
        ? t('admin.shipping.test.rangeOpen', { from: measureText(range.start) })
        : t('admin.shipping.test.rangeBetween', { from: measureText(range.start), to: measureText(range.end) });
});

const cityName = computed(() => cityOptions.value.find((option) => option.value === cityId.value)?.label ?? '');

const daysText = computed(() => {
    const { delivery_days_min: min, delivery_days_max: max } = props.method;

    if (min === null && max === null) {
        return null;
    }

    if (min !== null && max !== null && min !== max) {
        return t('admin.shipping.days.between', { min: formatNumber(min), max: formatNumber(max) });
    }

    const days = (min ?? max) as number;

    return days === 0 ? t('admin.shipping.days.sameDay') : t('admin.shipping.days.exactly', { n: formatNumber(days) });
});

const inputClass =
    'h-11 w-full rounded-[10px] border border-line-strong bg-surface px-3 text-sm text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft';
</script>

<template>
    <section
        class="flex flex-col gap-3.5 rounded-2xl border border-line bg-surface p-5"
        aria-labelledby="shipping-test-title"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="flex flex-col gap-1">
                <h2 id="shipping-test-title" class="text-base font-bold">{{ t('admin.shipping.test.title') }}</h2>
                <p class="text-xs leading-relaxed text-fg-muted">{{ t('admin.shipping.test.intro') }}</p>
            </div>
            <AppSpinner v-if="simulating" class="shrink-0" />
        </div>

        <div class="flex flex-col gap-2.5">
            <SelectField v-model="cityId" :label="t('admin.shipping.test.city')" :options="cityOptions" />
            <div>
                <label for="shipping-test-coupon" class="mb-1.5 block text-xs font-semibold">{{
                    t('admin.shipping.test.coupon')
                }}</label>
                <input
                    id="shipping-test-coupon"
                    v-model="couponCode"
                    dir="ltr"
                    autocomplete="off"
                    :placeholder="t('common.optional')"
                    :class="[inputClass, 'font-mono uppercase']"
                />
            </div>
        </div>

        <ul v-if="lines.length > 0" class="flex flex-col divide-y divide-line">
            <li v-for="line in lines" :key="line.productId" class="flex items-start gap-2.5 py-2.5">
                <img v-if="line.image" :src="line.image" alt="" class="size-9 shrink-0 rounded-lg object-cover" />
                <span
                    v-else
                    class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-surface-muted text-fg-muted"
                    ><AppIcon name="image" :size="16"
                /></span>
                <div class="flex min-w-0 grow flex-col gap-1.5">
                    <span class="truncate text-[13px] font-semibold">{{ line.productName }}</span>
                    <SelectField
                        v-if="line.variants.length > 1"
                        :model-value="String(line.variantId)"
                        :label="t('admin.shipping.test.variant')"
                        :options="variantOptions(line)"
                        @update:model-value="line.variantId = Number($event)"
                    />
                    <span
                        v-if="lineNote(line)"
                        :class="['text-[11px]', lineNote(line)?.warn ? 'text-warning' : 'text-fg-muted']"
                        >{{ lineNote(line)?.text }}</span
                    >
                </div>
                <div class="flex shrink-0 items-center gap-0.5">
                    <button
                        type="button"
                        class="flex size-7 items-center justify-center rounded-md border border-line text-fg-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                        :aria-label="t('admin.shipping.test.less', { name: line.productName })"
                        @click="line.quantity > 1 ? setQuantity(line, line.quantity - 1) : remove(line)"
                    >
                        <span v-if="line.quantity > 1" aria-hidden="true">−</span>
                        <AppIcon v-else name="trash" :size="13" />
                    </button>
                    <span class="w-7 text-center text-[13px]" aria-live="polite">{{
                        formatNumber(line.quantity)
                    }}</span>
                    <button
                        type="button"
                        class="flex size-7 items-center justify-center rounded-md border border-line text-fg-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                        :aria-label="t('admin.shipping.test.more', { name: line.productName })"
                        @click="setQuantity(line, line.quantity + 1)"
                    >
                        <AppIcon name="plus" :size="13" />
                    </button>
                </div>
            </li>
        </ul>
        <p
            v-else
            class="rounded-xl border border-dashed border-line-strong px-4 py-6 text-center text-[13px] text-fg-muted"
        >
            {{ t('admin.shipping.test.empty') }}
        </p>

        <button
            type="button"
            class="flex h-10 items-center justify-center gap-1.5 rounded-xl border border-line text-[13px] font-semibold hover:bg-surface-muted focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            @click="picking = true"
        >
            <AppIcon name="package" :size="16" />
            {{ lines.length > 0 ? t('admin.shipping.test.change') : t('admin.shipping.test.choose') }}
        </button>

        <FormAlert v-if="error" :message="error" />
        <p v-else-if="lines.length > 0 && !valid" class="text-xs text-fg-muted">
            {{ t('admin.shipping.test.invalid') }}
        </p>

        <dl v-if="simulation && shipping" class="flex flex-col gap-2 text-[13px]">
            <template v-if="method.type === 'weight' && simulation.weight_grams !== null">
                <div class="flex justify-between gap-3">
                    <dt class="text-fg-muted">{{ t('admin.shipping.test.itemsWeight') }}</dt>
                    <dd>{{ kilos(simulation.weight_grams) }}</dd>
                </div>
                <div v-if="method.packaging_weight_grams" class="flex justify-between gap-3">
                    <dt class="text-fg-muted">{{ t('admin.shipping.test.packaging') }}</dt>
                    <dd>{{ kilos(method.packaging_weight_grams) }}</dd>
                </div>
            </template>
            <div v-if="shipping.measure !== null" class="flex justify-between gap-3 font-semibold">
                <dt>
                    {{
                        method.type === 'weight'
                            ? t('admin.shipping.test.orderWeight')
                            : t('admin.shipping.test.orderVolume')
                    }}
                </dt>
                <dd>{{ measureText(shipping.measure) }}</dd>
            </div>
            <div
                v-if="rangeText && shipping.range"
                class="flex justify-between gap-3 rounded-lg bg-surface-muted px-2.5 py-2 text-xs font-semibold"
            >
                <dt>{{ rangeText }}</dt>
                <dd>{{ money.format(shipping.range.fee) }}</dd>
            </div>
            <div
                v-if="method.type === 'courier' && shipping.available"
                class="flex justify-between gap-3 rounded-lg bg-surface-muted px-2.5 py-2 text-xs font-semibold"
            >
                <dt>{{ cityName }}</dt>
                <dd>{{ money.format(shipping.original_fee ?? 0) }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-fg-muted">{{ t('admin.shipping.test.itemsTotal') }}</dt>
                <dd>{{ money.format(simulation.items_total) }}</dd>
            </div>
            <p v-if="simulation.coupon_discount > 0" class="text-xs text-fg-muted">
                {{ t('admin.shipping.test.couponDiscount', { amount: money.format(simulation.coupon_discount) }) }}
            </p>
            <p v-if="simulation.coupon_problem" class="text-xs text-warning">
                {{ t(`admin.shipping.test.couponProblems.${simulation.coupon_problem}`) }}
            </p>

            <p
                v-if="!shipping.available"
                class="flex items-start gap-2 rounded-lg bg-warning-soft px-2.5 py-2 text-xs font-semibold text-warning"
            >
                <AppIcon name="alert" :size="15" class="mt-px shrink-0" />
                {{ t(`admin.shipping.test.reasons.${shipping.reason}`) }}
            </p>
            <template v-else>
                <p v-if="shipping.free_reason" class="text-xs font-semibold text-success">
                    {{ t(`admin.shipping.test.free.${shipping.free_reason}`) }}
                </p>
                <p v-else-if="shipping.remaining_for_free !== null" class="text-xs text-warning">
                    {{ t('admin.shipping.test.remaining', { amount: money.format(shipping.remaining_for_free) }) }}
                </p>
                <div class="flex justify-between gap-3 border-t border-line pt-2 text-[15px] font-bold">
                    <dt>{{ t('admin.shipping.test.fee') }}</dt>
                    <dd>
                        <s
                            v-if="shipping.free_reason && shipping.original_fee"
                            class="me-1.5 text-xs font-normal text-fg-muted"
                            >{{ money.format(shipping.original_fee) }}</s
                        >{{ money.format(shipping.fee ?? 0) }}
                    </dd>
                </div>
                <div
                    v-if="shipping.fee_on_delivery && (shipping.collect_on_delivery ?? 0) > 0"
                    class="flex justify-between gap-3 rounded-lg bg-warning-soft px-2.5 py-2 font-semibold text-warning"
                >
                    <dt>{{ t('admin.shipping.test.collect') }}</dt>
                    <dd>{{ money.format(shipping.collect_on_delivery ?? 0) }}</dd>
                </div>
                <p v-if="shipping.fee_on_delivery" class="text-[11px] text-fg-muted">
                    {{ t('admin.shipping.test.payableNow', { amount: money.format(shipping.payable_now ?? 0) }) }}
                </p>
            </template>
        </dl>

        <div
            v-if="simulation && shipping?.available"
            class="flex flex-col gap-2 border-t border-line pt-3.5"
            aria-labelledby="shipping-preview-title"
        >
            <h3 id="shipping-preview-title" class="text-sm font-bold">{{ t('admin.shipping.test.previewTitle') }}</h3>
            <p class="text-[11px] text-fg-muted">{{ t('admin.shipping.test.previewIntro') }}</p>
            <div class="flex gap-2.5 rounded-xl border-2 border-primary p-3">
                <span class="mt-0.5 size-4 shrink-0 rounded-full border-4 border-primary" aria-hidden="true" />
                <span class="flex min-w-0 grow flex-col gap-0.5">
                    <span class="flex justify-between gap-2 text-sm font-semibold">
                        <span class="truncate">{{ method.name || t('admin.shipping.newTitle') }}</span>
                        <span class="shrink-0">{{
                            shipping.fee === 0
                                ? t('admin.shipping.test.freeLabel')
                                : shipping.fee_on_delivery
                                  ? t('admin.shipping.payment.onDelivery')
                                  : money.format(shipping.fee ?? 0)
                        }}</span>
                    </span>
                    <span v-if="daysText" class="text-xs text-fg-muted">{{ daysText }}</span>
                    <span
                        v-if="shipping.fee_on_delivery && (shipping.collect_on_delivery ?? 0) > 0"
                        class="text-xs text-warning"
                        >{{
                            t('admin.shipping.test.previewCollect', {
                                amount: money.format(shipping.collect_on_delivery ?? 0),
                            })
                        }}</span
                    >
                    <span v-else-if="shipping.remaining_for_free !== null" class="text-xs text-warning">{{
                        t('admin.shipping.test.previewRemaining', { amount: money.format(shipping.remaining_for_free) })
                    }}</span>
                </span>
            </div>
        </div>

        <ProductPickerDialog
            :open="picking"
            mode="choose"
            :title="t('admin.shipping.test.pickTitle')"
            :intro="t('admin.shipping.test.pickIntro', { max: formatNumber(MAX_LINES) })"
            :initial="lines.map((line) => ({ id: line.productId, name: line.productName }))"
            :busy="adding"
            :error="addError"
            @close="picking = false"
            @confirm="choose"
        />
    </section>
</template>
