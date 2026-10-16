<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { toApiError } from '@/shared/http/errors';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SelectField from '@/shared/ui/SelectField.vue';
import ProductPickerDialog, { type PickedProduct } from '../../products/components/ProductPickerDialog.vue';
import { useMoney } from '../../settings/store';
import { couponsApi, saleEventsApi } from '../api';
import type { CouponSimulation, SaleEventVariant, SaveCouponPayload } from '../types';

/**
 * "Sample order" beside the coupon form: the unsaved coupon applied to a cart of sample products,
 * worked out by the server with the rules checkout uses. Nothing is saved; the cart lives as long
 * as the page.
 */
const props = defineProps<{ coupon: SaveCouponPayload; valid: boolean }>();

/** Most products the sample cart holds. */
const MAX_LINES = 8;

const { t } = useI18n();
const { formatNumber } = useLocale();
const money = useMoney();

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
    line.variants.map((variant) => ({
        value: String(variant.id),
        label: `${variant.label || variant.sku} · ${money.format(variant.current_price)}`,
    }));

const variantOf = (line: Line): SaleEventVariant['variant'] | undefined =>
    line.variants.find((variant) => variant.id === line.variantId);

// ---- simulation ----

const simulation = ref<CouponSimulation | null>(null);
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
        const result = await couponsApi.simulate(
            props.coupon,
            lines.value.map((line) => ({ variant_id: line.variantId, quantity: line.quantity })),
        );

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
    () => JSON.stringify([props.coupon, props.valid, lines.value.map((line) => [line.variantId, line.quantity])]),
    () => {
        clearTimeout(timer);
        timer = setTimeout(simulate, 500);
    },
);
onBeforeUnmount(() => clearTimeout(timer));

const lineResult = (line: Line): CouponSimulation['lines'][number] | undefined =>
    simulation.value?.lines.find((result) => result.variant_id === line.variantId);

function lineNote(line: Line): { text: string; eligible: boolean } | null {
    const result = lineResult(line);

    if (!result) {
        return null;
    }

    return result.eligible
        ? { text: t('admin.discounts.coupons.sample.eligible'), eligible: true }
        : { text: t(`admin.discounts.coupons.sample.reasons.${result.reason ?? 'not_targeted'}`), eligible: false };
}

const discountLabel = computed(() =>
    props.coupon.type === 'percent' && props.coupon.value !== null
        ? t('admin.discounts.coupons.sample.discountPercent', { n: formatNumber(props.coupon.value) })
        : t('admin.discounts.coupons.sample.discount'),
);
const capped = computed(
    () => simulation.value !== null && simulation.value.uncapped_discount > simulation.value.discount,
);
</script>

<template>
    <section
        class="flex flex-col gap-3.5 rounded-2xl border border-line bg-surface p-5"
        aria-labelledby="coupon-sample-title"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="flex flex-col gap-1">
                <h2 id="coupon-sample-title" class="text-base font-bold">
                    {{ t('admin.discounts.coupons.sample.title') }}
                </h2>
                <p class="text-xs leading-relaxed text-fg-muted">{{ t('admin.discounts.coupons.sample.intro') }}</p>
            </div>
            <AppSpinner v-if="simulating" class="shrink-0" />
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
                        :label="t('admin.discounts.coupons.sample.variant')"
                        :options="variantOptions(line)"
                        @update:model-value="line.variantId = Number($event)"
                    />
                    <span
                        v-if="lineNote(line)"
                        :class="['text-[11px]', lineNote(line)?.eligible ? 'text-success' : 'text-fg-muted']"
                        >{{ lineNote(line)?.text }}</span
                    >
                </div>
                <div class="flex shrink-0 flex-col items-end gap-1.5">
                    <span class="text-[13px] whitespace-nowrap">{{
                        money.format(lineResult(line)?.total ?? (variantOf(line)?.current_price ?? 0) * line.quantity)
                    }}</span>
                    <div class="flex items-center gap-0.5">
                        <button
                            type="button"
                            class="flex size-7 items-center justify-center rounded-md border border-line text-fg-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                            :aria-label="t('admin.discounts.coupons.sample.less', { name: line.productName })"
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
                            :aria-label="t('admin.discounts.coupons.sample.more', { name: line.productName })"
                            @click="setQuantity(line, line.quantity + 1)"
                        >
                            <AppIcon name="plus" :size="13" />
                        </button>
                    </div>
                </div>
            </li>
        </ul>
        <p
            v-else
            class="rounded-xl border border-dashed border-line-strong px-4 py-6 text-center text-[13px] text-fg-muted"
        >
            {{ t('admin.discounts.coupons.sample.empty') }}
        </p>

        <button
            type="button"
            class="flex h-10 items-center justify-center gap-1.5 rounded-xl border border-line text-[13px] font-semibold hover:bg-surface-muted focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            @click="picking = true"
        >
            <AppIcon name="package" :size="16" />
            {{
                lines.length > 0
                    ? t('admin.discounts.coupons.sample.change')
                    : t('admin.discounts.coupons.sample.choose')
            }}
        </button>

        <FormAlert v-if="error" :message="error" />
        <p v-else-if="lines.length > 0 && !valid" class="text-xs text-fg-muted">
            {{ t('admin.discounts.coupons.sample.invalid') }}
        </p>

        <dl v-if="simulation" class="flex flex-col gap-2 text-[13px]">
            <div class="flex justify-between gap-3">
                <dt class="text-fg-muted">{{ t('admin.discounts.coupons.sample.subtotal') }}</dt>
                <dd>{{ money.format(simulation.subtotal) }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-fg-muted">{{ t('admin.discounts.coupons.sample.eligibleSubtotal') }}</dt>
                <dd>{{ money.format(simulation.eligible_subtotal) }}</dd>
            </div>
            <p v-if="simulation.problem" class="text-xs text-warning">
                {{ t(`admin.discounts.coupons.sample.problems.${simulation.problem}`) }}
            </p>
            <template v-else>
                <div v-if="simulation.discount > 0" class="flex justify-between gap-3 font-semibold text-success">
                    <dt>{{ discountLabel }}</dt>
                    <dd>− {{ money.format(simulation.discount) }}</dd>
                </div>
                <p v-if="capped" class="text-[11px] text-fg-muted">
                    {{
                        t('admin.discounts.coupons.sample.capped', {
                            would: money.format(simulation.uncapped_discount),
                            cap: money.format(simulation.discount),
                        })
                    }}
                </p>
                <div v-if="simulation.free_shipping" class="flex justify-between gap-3 font-semibold text-success">
                    <dt>{{ t('admin.discounts.coupons.types.free_shipping') }}</dt>
                    <dd><AppIcon name="check" :size="16" /></dd>
                </div>
            </template>
            <div class="flex justify-between gap-3 border-t border-line pt-2 text-[15px] font-bold">
                <dt>{{ t('admin.discounts.coupons.sample.total') }}</dt>
                <dd>{{ money.format(simulation.total) }}</dd>
            </div>
        </dl>

        <ProductPickerDialog
            :open="picking"
            mode="choose"
            :title="t('admin.discounts.coupons.sample.pickTitle')"
            :intro="t('admin.discounts.coupons.sample.pickIntro', { max: formatNumber(MAX_LINES) })"
            :initial="lines.map((line) => ({ id: line.productId, name: line.productName }))"
            :busy="adding"
            :error="addError"
            @close="picking = false"
            @confirm="choose"
        />
    </section>
</template>
