<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import NumberField from '@/shared/ui/NumberField.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import { useMoney } from '../../settings/store';
import { applyDiscount, type DiscountField, discountOf, type EventRow, MAX_PERCENT } from '../saleEvents';

/**
 * The same discount for the selected variants. With an amount off or a final price each variant
 * keeps its own price as the base; variants it can't apply to are listed as skipped.
 */
const props = defineProps<{ open: boolean; rows: EventRow[] }>();
const emit = defineEmits<{ close: []; apply: [field: DiscountField, value: number] }>();

const { t } = useI18n();
const { formatNumber } = useLocale();
const money = useMoney();

const field = ref<DiscountField>('percent');
const value = ref<number | null>(null);

watch(
    () => props.open,
    (open) => {
        if (open) {
            value.value = null;
        }
    },
);

const fieldOptions = computed(() => [
    { value: 'percent' as const, label: t('admin.discounts.events.fields.percent') },
    { value: 'off' as const, label: t('admin.discounts.events.fields.off') },
    { value: 'final' as const, label: t('admin.discounts.events.fields.final') },
]);

const valid = computed(
    () => value.value !== null && value.value > 0 && (field.value !== 'percent' || value.value <= MAX_PERCENT),
);

/** Each selected variant with its price after the discount, or null when it would be skipped. */
const result = computed(() => {
    if (!valid.value || value.value === null) {
        return [];
    }

    const ids = new Set(props.rows.map((row) => row.variantId));
    const { rows, skipped } = applyDiscount(props.rows, ids, field.value, value.value);
    const skippedIds = new Set(skipped);

    return rows.map((row) => ({
        row,
        final: skippedIds.has(row.variantId) ? null : row.price - (discountOf(row, money.rialsPerUnit.value) ?? 0),
    }));
});
const applicable = computed(() => result.value.filter((item) => item.final !== null).length);

function apply(): void {
    if (valid.value && value.value !== null) {
        emit('apply', field.value, value.value);
    }
}
</script>

<template>
    <AppDialog
        :open="open"
        :title="t('admin.discounts.events.bulk.discountTitle', { n: formatNumber(rows.length) })"
        icon="tag"
        @close="emit('close')"
    >
        <div class="flex flex-col gap-4">
            <p class="text-sm leading-relaxed text-fg-muted">{{ t('admin.discounts.events.bulk.discountIntro') }}</p>
            <SegmentedControl
                v-model="field"
                :label="t('admin.discounts.events.bulk.discountType')"
                :options="fieldOptions"
            />
            <NumberField
                v-model="value"
                :label="t(`admin.discounts.events.fields.${field}`)"
                :hint="field === 'percent' ? t('admin.discounts.events.bulk.percentHint') : undefined"
                :unit="field === 'percent' ? '%' : money.unit.value"
                :factor="field === 'percent' ? 1 : money.rialsPerUnit.value"
            />
            <div v-if="result.length > 0" class="flex flex-col gap-1.5">
                <span class="text-sm font-semibold">{{ t('admin.discounts.events.bulk.result') }}</span>
                <ul class="max-h-56 divide-y divide-line overflow-y-auto rounded-xl border border-line text-[13px]">
                    <li v-for="item in result" :key="item.row.variantId" class="flex items-center gap-3 px-3 py-2">
                        <span class="min-w-0 grow truncate">
                            {{ item.row.productName }}<template v-if="item.row.label"> · {{ item.row.label }}</template>
                        </span>
                        <span v-if="item.final === null" class="shrink-0 text-warning">{{
                            t('admin.discounts.events.bulk.skipped')
                        }}</span>
                        <span v-else class="flex shrink-0 items-center gap-1.5 text-fg-muted">
                            <span class="line-through">{{ money.format(item.row.price) }}</span>
                            <AppIcon name="arrowForward" :size="14" flip-in-rtl />
                            <span class="font-semibold text-fg">{{ money.format(item.final) }}</span>
                        </span>
                    </li>
                </ul>
            </div>
        </div>
        <template #footer>
            <AppButton size="md" :disabled="!valid || applicable === 0" @click="apply">{{
                t('admin.discounts.events.bulk.applyTo', { n: formatNumber(applicable) })
            }}</AppButton>
            <AppButton variant="secondary" size="md" @click="emit('close')">{{ t('common.cancel') }}</AppButton>
        </template>
    </AppDialog>
</template>
