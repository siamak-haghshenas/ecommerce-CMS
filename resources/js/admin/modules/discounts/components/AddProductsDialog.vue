<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toApiError } from '@/shared/http/errors';
import NumberField from '@/shared/ui/NumberField.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import ProductPickerDialog, { type PickedProduct } from '../../products/components/ProductPickerDialog.vue';
import { useMoney } from '../../settings/store';
import { saleEventsApi } from '../api';
import { type EventRow, rowsFromVariants } from '../saleEvents';

/**
 * "Add products" of the sale event editor: the product picker, with an optional starting discount.
 * Each chosen product is added with all its enabled variants; products already in the event are
 * marked and can't be chosen.
 */
const props = defineProps<{ open: boolean; productIds: Set<number>; variantIds: Set<number> }>();
const emit = defineEmits<{ close: []; add: [rows: EventRow[]] }>();

/** The most products the variants endpoint takes at once (MatchingProductsAction::LIMIT). */
const CHUNK = 500;

const { t } = useI18n();
const money = useMoney();

const startField = ref<'percent' | 'off'>('percent');
const startValue = ref<number | null>(null);
const fieldOptions = computed(() => [
    { value: 'percent' as const, label: t('admin.discounts.events.fields.percent') },
    { value: 'off' as const, label: t('admin.discounts.events.fields.off') },
]);
const adding = ref(false);
const error = ref<string | null>(null);

watch(
    () => props.open,
    (open) => {
        if (open) {
            startValue.value = null;
            error.value = null;
        }
    },
);

async function add(products: PickedProduct[]): Promise<void> {
    adding.value = true;
    error.value = null;

    try {
        const ids = products.map((product) => product.id);
        const chunks = Array.from({ length: Math.ceil(ids.length / CHUNK) }, (_, index) =>
            ids.slice(index * CHUNK, (index + 1) * CHUNK),
        );
        const variants = (await Promise.all(chunks.map((chunk) => saleEventsApi.variants(chunk)))).flat();
        emit('add', rowsFromVariants(variants, props.variantIds, { field: startField.value, value: startValue.value }));
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        adding.value = false;
    }
}
</script>

<template>
    <ProductPickerDialog
        :open="open"
        :title="t('admin.discounts.events.add.title')"
        :intro="t('admin.discounts.events.add.intro')"
        :locked="productIds"
        :locked-label="t('admin.discounts.events.add.already')"
        :busy="adding"
        :error="error"
        @close="emit('close')"
        @confirm="add"
    >
        <div class="flex flex-col gap-2">
            <span class="text-sm font-semibold"
                >{{ t('admin.discounts.events.add.start') }}
                <span class="font-normal text-fg-muted">{{ t('common.optional') }}</span></span
            >
            <div class="flex flex-wrap items-start gap-3">
                <div class="w-56">
                    <SegmentedControl
                        v-model="startField"
                        :label="t('admin.discounts.events.add.start')"
                        :options="fieldOptions"
                    />
                </div>
                <div class="w-44">
                    <NumberField
                        v-model="startValue"
                        :label="t('admin.discounts.events.add.start')"
                        compact
                        :unit="startField === 'percent' ? '%' : money.unit.value"
                        :factor="startField === 'percent' ? 1 : money.rialsPerUnit.value"
                    />
                </div>
            </div>
        </div>
    </ProductPickerDialog>
</template>
