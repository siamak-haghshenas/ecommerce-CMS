<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import NumberField from '@/shared/ui/NumberField.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import { useMoney } from '../../settings/store';
import { type BulkChange, MAX_PER_ORDER, MAX_QUANTITY, type PromotionRow } from '../promotions';
import { type DiscountField, MAX_PERCENT } from '../saleEvents';

/**
 * Bulk edit of the selected promotion rows: whatever is filled in is set on every selected row
 * (discount as a percentage, an amount off or an off price; order limit; discounted quantity; start;
 * end); an empty input leaves that field as it is.
 */
const props = defineProps<{ open: boolean; rows: PromotionRow[] }>();
const emit = defineEmits<{ close: []; apply: [change: BulkChange] }>();

const { t } = useI18n();
const { formatNumber } = useLocale();
const money = useMoney();

const field = ref<DiscountField>('percent');
const value = ref<number | null>(null);
const maxPerOrder = ref<number | null>(null);
const quantityLimit = ref<number | null>(null);
const startsAt = ref('');
const endsAt = ref('');

watch(
    () => props.open,
    (open) => {
        if (open) {
            field.value = 'percent';
            value.value = null;
            maxPerOrder.value = null;
            quantityLimit.value = null;
            startsAt.value = '';
            endsAt.value = '';
        }
    },
);

/** An off price only makes sense when every selected row has the same price. */
const samePrice = computed(() => new Set(props.rows.map((row) => row.price)).size === 1);
const fieldOptions = computed(() =>
    (['percent', 'off', 'final'] as const)
        .filter((option) => option !== 'final' || samePrice.value)
        .map((option) => ({ value: option, label: t(`admin.discounts.promotions.bulk.types.${option}`) })),
);

watch(samePrice, (same) => {
    if (!same && field.value === 'final') {
        field.value = 'percent';
    }
});

const outOfRange = (number: number | null, max: number): boolean => number !== null && (number < 1 || number > max);
const errors = computed(() => ({
    discount: value.value !== null && (value.value < 1 || (field.value === 'percent' && value.value > MAX_PERCENT)),
    maxPerOrder: outOfRange(maxPerOrder.value, MAX_PER_ORDER),
    quantityLimit: outOfRange(quantityLimit.value, MAX_QUANTITY),
    dates: startsAt.value !== '' && endsAt.value !== '' && endsAt.value <= startsAt.value,
}));
const filled = computed(
    () =>
        value.value !== null ||
        maxPerOrder.value !== null ||
        quantityLimit.value !== null ||
        startsAt.value !== '' ||
        endsAt.value !== '',
);
const valid = computed(() => filled.value && !Object.values(errors.value).some(Boolean));

function apply(): void {
    if (!valid.value) {
        return;
    }

    emit('apply', {
        ...(value.value !== null ? { discount: { field: field.value, value: value.value } } : {}),
        ...(maxPerOrder.value !== null ? { maxPerOrder: maxPerOrder.value } : {}),
        ...(quantityLimit.value !== null ? { quantityLimit: quantityLimit.value } : {}),
        ...(startsAt.value !== '' ? { startsAt: startsAt.value } : {}),
        ...(endsAt.value !== '' ? { endsAt: endsAt.value } : {}),
    });
}

const dateClass =
    'h-11 w-full rounded-lg border border-line-strong bg-surface px-3 text-sm text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft';
</script>

<template>
    <AppDialog
        :open="open"
        :title="t('admin.discounts.promotions.bulk.title', { n: formatNumber(rows.length) })"
        icon="pen"
        @close="emit('close')"
    >
        <div class="flex flex-col gap-4">
            <p class="text-sm leading-relaxed text-fg-muted">{{ t('admin.discounts.promotions.bulk.intro') }}</p>

            <section class="flex flex-col gap-3">
                <span class="text-sm font-semibold">{{ t('admin.discounts.promotions.bulk.discount') }}</span>
                <SegmentedControl
                    v-model="field"
                    :label="t('admin.discounts.promotions.bulk.discount')"
                    :options="fieldOptions"
                />
                <NumberField
                    v-model="value"
                    :label="t(`admin.discounts.promotions.bulk.types.${field}`)"
                    :placeholder="t('admin.discounts.promotions.bulk.unchanged')"
                    :unit="field === 'percent' ? '%' : money.unit.value"
                    :factor="field === 'percent' ? 1 : money.rialsPerUnit.value"
                    :hint="
                        samePrice
                            ? t('admin.discounts.promotions.bulk.ownPrice')
                            : t('admin.discounts.promotions.bulk.finalHint')
                    "
                    :error="
                        errors.discount
                            ? t(
                                  field === 'percent'
                                      ? 'admin.discounts.promotions.problems.percent_range'
                                      : 'admin.discounts.promotions.problems.required',
                              )
                            : undefined
                    "
                    optional
                />
            </section>

            <div class="grid gap-4 sm:grid-cols-2">
                <NumberField
                    v-model="maxPerOrder"
                    :label="t('admin.discounts.promotions.columns.maxPerOrder')"
                    :placeholder="t('admin.discounts.promotions.bulk.unchanged')"
                    :unit="t('admin.discounts.promotions.unit')"
                    :error="errors.maxPerOrder ? t('admin.discounts.promotions.problems.limit_range') : undefined"
                    optional
                />
                <NumberField
                    v-model="quantityLimit"
                    :label="t('admin.discounts.promotions.columns.quantity')"
                    :placeholder="t('admin.discounts.promotions.bulk.unchanged')"
                    :unit="t('admin.discounts.promotions.unit')"
                    :hint="t('admin.discounts.promotions.bulk.perRow')"
                    :error="errors.quantityLimit ? t('admin.discounts.promotions.problems.quantity_range') : undefined"
                    optional
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="promotion-bulk-start" class="mb-2 block text-sm font-semibold">{{
                        t('admin.discounts.promotions.columns.startsAt')
                    }}</label>
                    <input id="promotion-bulk-start" v-model="startsAt" type="datetime-local" :class="dateClass" />
                </div>
                <div>
                    <label for="promotion-bulk-end" class="mb-2 block text-sm font-semibold">{{
                        t('admin.discounts.promotions.columns.endsAt')
                    }}</label>
                    <input id="promotion-bulk-end" v-model="endsAt" type="datetime-local" :class="dateClass" />
                </div>
            </div>
            <p v-if="errors.dates" class="-mt-2 text-[13px] text-danger">
                {{ t('admin.discounts.promotions.problems.dates_order') }}
            </p>
        </div>
        <template #footer>
            <AppButton size="md" :disabled="!valid" @click="apply">{{
                t('admin.discounts.promotions.bulk.apply', { n: formatNumber(rows.length) })
            }}</AppButton>
            <AppButton variant="secondary" size="md" @click="emit('close')">{{ t('common.cancel') }}</AppButton>
        </template>
    </AppDialog>
</template>
