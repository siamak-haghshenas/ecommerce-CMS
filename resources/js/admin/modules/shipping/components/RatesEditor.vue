<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import AppIcon from '@/shared/ui/AppIcon.vue';
import NumberField from '@/shared/ui/NumberField.vue';
import { useMoney } from '../../settings/store';
import {
    addRate,
    MAX_RATES,
    MEASURE_DECIMALS,
    MEASURE_FACTOR,
    type RateRow,
    rateStarts,
    removeRate,
} from '../shipping';

/**
 * The ranges of a weight (kilos) or volume (litres) method. Each row starts where the previous
 * one ends, so only its end and fee are typed; the last end may stay empty (no upper limit).
 * `errors` are keyed like the server's (`rates.2.end`).
 */
const props = defineProps<{ type: 'weight' | 'volume'; errors: Record<string, string | undefined> }>();
const rates = defineModel<RateRow[]>({ required: true });

const { t } = useI18n();
const { formatNumber } = useLocale();
const money = useMoney();

const unit = computed(() =>
    props.type === 'weight' ? t('admin.shipping.units.kg') : t('admin.shipping.units.litres'),
);
const starts = computed(() => rateStarts(rates.value));

const startText = (index: number): string => {
    const start = starts.value[index];

    return start === null || start === undefined ? '…' : formatNumber(start / MEASURE_FACTOR);
};

function setEnd(index: number, end: number | null): void {
    rates.value = rates.value.map((rate, other) => (other === index ? { ...rate, end } : rate));
}

function setFee(index: number, fee: number | null): void {
    rates.value = rates.value.map((rate, other) => (other === index ? { ...rate, fee } : rate));
}

const lastEnd = computed(() => rates.value[rates.value.length - 1]?.end ?? null);
</script>

<template>
    <div :class="['overflow-hidden rounded-xl border', errors.rates ? 'border-danger' : 'border-line']">
        <div
            class="hidden grid-cols-[2.5rem_repeat(3,minmax(0,1fr))_2.75rem] items-center gap-3 bg-surface-muted/70 px-3.5 py-2.5 text-xs font-semibold text-fg-muted sm:grid"
        >
            <span>#</span>
            <span>{{ t('admin.shipping.rates.from', { unit }) }}</span>
            <span>{{ t('admin.shipping.rates.to', { unit }) }}</span>
            <span>{{ t('admin.shipping.rates.fee') }}</span>
            <span />
        </div>
        <ol class="flex flex-col divide-y divide-line">
            <li
                v-for="(rate, index) in rates"
                :key="index"
                class="grid grid-cols-2 items-start gap-3 px-3.5 py-2.5 sm:grid-cols-[2.5rem_repeat(3,minmax(0,1fr))_2.75rem]"
            >
                <span class="flex h-10 items-center text-[13px] text-fg-muted">{{ formatNumber(index + 1) }}</span>
                <span
                    class="flex h-10 items-center justify-end rounded-lg bg-surface-muted/70 px-3 text-sm text-fg-muted sm:justify-start"
                    :aria-label="t('admin.shipping.rates.fromLabel', { n: formatNumber(index + 1) })"
                    >{{ startText(index) }} <span class="ms-1.5 text-xs sm:hidden">{{ unit }}</span></span
                >
                <NumberField
                    :model-value="rate.end"
                    :label="t('admin.shipping.rates.toLabel', { n: formatNumber(index + 1) })"
                    :unit="unit"
                    :factor="MEASURE_FACTOR"
                    :decimals="MEASURE_DECIMALS"
                    :placeholder="index === rates.length - 1 ? t('admin.shipping.rates.noLimit') : undefined"
                    compact
                    :error="errors[`rates.${index}.end`]"
                    @update:model-value="setEnd(index, $event)"
                />
                <NumberField
                    :model-value="rate.fee"
                    :label="t('admin.shipping.rates.feeLabel', { n: formatNumber(index + 1) })"
                    :unit="money.unit.value"
                    :factor="money.rialsPerUnit.value"
                    compact
                    :error="errors[`rates.${index}.fee`] ?? errors[`rates.${index}.start`]"
                    @update:model-value="setFee(index, $event)"
                />
                <button
                    type="button"
                    class="flex size-10 items-center justify-center justify-self-end rounded-lg text-fg-muted hover:bg-surface-muted hover:text-danger focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none disabled:opacity-40"
                    :disabled="rates.length === 1"
                    :aria-label="t('admin.shipping.rates.remove', { n: formatNumber(index + 1) })"
                    @click="rates = removeRate(rates, index)"
                >
                    <AppIcon name="trash" :size="16" />
                </button>
            </li>
        </ol>
        <div class="border-t border-line px-3.5 py-2.5">
            <button
                type="button"
                class="flex h-9 items-center gap-1.5 rounded-[10px] border border-dashed border-line-strong px-3 text-[13px] font-semibold hover:bg-surface-muted focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none disabled:opacity-50"
                :disabled="rates.length >= MAX_RATES"
                @click="rates = addRate(rates)"
            >
                <AppIcon name="plus" :size="16" />
                {{
                    lastEnd === null
                        ? t('admin.shipping.rates.add')
                        : t('admin.shipping.rates.addFrom', { n: formatNumber(lastEnd / MEASURE_FACTOR), unit })
                }}
            </button>
        </div>
    </div>
    <p v-if="errors.rates" class="text-[13px] text-danger">{{ errors.rates }}</p>
    <ul class="flex list-disc flex-col gap-1 ps-5 text-xs leading-relaxed text-fg-muted">
        <li>{{ t('admin.shipping.rates.hintContiguous') }}</li>
        <li>{{ t('admin.shipping.rates.hintBoundary', { unit }) }}</li>
        <li>{{ t('admin.shipping.rates.hintLast') }}</li>
    </ul>
</template>
