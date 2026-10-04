<script setup lang="ts">
import { computed, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import NumberField from '@/shared/ui/NumberField.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import type { Option } from '../../attributes/types';
import { useMoney } from '../../settings/store';
import type { ProductImage, ProductOption, ProductType } from '../types';
import { combinations, newRow, regenerate, rowLabel, suggestSku, type VariantRow } from '../variants';

/** The options a variable product varies by, in order, with the values it uses. */
export interface ChosenOption {
    optionId: number;
    valueIds: number[];
}

const MAX_OPTIONS = 3;
const MAX_VARIANTS = 300;

/**
 * Pricing, stock and variants. A simple product edits its single variant; a variable product
 * picks options and values, and a variant is generated for every combination (existing variants
 * keep their price and stock). Errors come from the API as `variants.{index}.{field}`.
 */
const props = defineProps<{
    options: Option[];
    images: ProductImage[];
    trackStock: boolean;
    skuBase: string;
    errors: Record<string, string[]>;
}>();
const type = defineModel<ProductType>('type', { required: true });
const chosen = defineModel<ChosenOption[]>('chosen', { required: true });
const rows = defineModel<VariantRow[]>('rows', { required: true });

const { t } = useI18n();
const { formatNumber } = useLocale();
const { unit, rialsPerUnit } = useMoney();
const id = useId();

const typeOptions = computed(() => [
    { value: 'simple' as const, label: t('admin.products.variants.simple') },
    { value: 'variable' as const, label: t('admin.products.variants.variable') },
]);

const optionsById = computed(() => new Map(props.options.map((option) => [option.id, option])));
/** The chosen options with all their values, in the chosen order (for labels). */
const chosenOptions = computed<ProductOption[]>(() =>
    chosen.value
        .map((item) => optionsById.value.get(item.optionId))
        .filter((option): option is Option => !!option)
        .map((option) => ({ ...option, values: option.values })),
);
const available = computed(() =>
    props.options.filter((option) => !chosen.value.some((item) => item.optionId === option.id)),
);
const toAdd = ref('');

const comboCount = computed(() =>
    chosen.value.reduce((count, item) => count * item.valueIds.length, chosen.value.length > 0 ? 1 : 0),
);

function error(index: number, field: string): string | undefined {
    return props.errors[`variants.${index}.${field}`]?.[0];
}

// ---- switching type ----

/** Only the admin's switch converts the rows (loading a product just sets the type). */
const typeChoice = computed<ProductType>({
    get: () => type.value,
    set: (next) => {
        if (next === type.value) {
            return;
        }

        type.value = next;

        if (next === 'simple') {
            // Keep one variant: the first (with its id, price and stock).
            rows.value = [rows.value[0] ?? newRow()].map((row) => ({ ...row, optionValueIds: [] }));
            chosen.value = [];
        }
        // To variable: the simple row stays as the price template until options are chosen.
    },
});

// ---- options and the generated grid ----

/**
 * Set the chosen options and rebuild the grid from them. The new list is passed on rather than
 * read back from the model: a defineModel value only updates after the parent re-renders.
 */
function choose(next: ChosenOption[]): void {
    chosen.value = next;

    const valueIdsPerOption = next.map((item) => {
        const option = optionsById.value.get(item.optionId);

        // In the option's own value order, whatever order they were picked in.
        return (option?.values ?? []).map((value) => value.id).filter((valueId) => item.valueIds.includes(valueId));
    });

    rows.value =
        next.length === 0 || valueIdsPerOption.some((values) => values.length === 0)
            ? []
            : regenerate(rows.value, combinations(valueIdsPerOption));
}

function addOption(): void {
    const option = optionsById.value.get(Number(toAdd.value));
    toAdd.value = '';

    if (option && chosen.value.length < MAX_OPTIONS) {
        choose([...chosen.value, { optionId: option.id, valueIds: option.values.map((value) => value.id) }]);
    }
}

function removeOption(optionId: number): void {
    choose(chosen.value.filter((item) => item.optionId !== optionId));
}

function toggleValue(optionId: number, valueId: number): void {
    choose(
        chosen.value.map((item) =>
            item.optionId !== optionId
                ? item
                : {
                      ...item,
                      valueIds: item.valueIds.includes(valueId)
                          ? item.valueIds.filter((id) => id !== valueId)
                          : [...item.valueIds, valueId],
                  },
        ),
    );
}

// Suggested SKUs follow the slug and values until the admin types one.
watch(
    [rows, () => props.skuBase],
    () => {
        const slugs = new Map(props.options.flatMap((option) => option.values.map((value) => [value.id, value.slug])));

        for (const row of rows.value) {
            if (!row.skuEdited) {
                row.sku = suggestSku(
                    props.skuBase,
                    row.optionValueIds.map((valueId) => slugs.get(valueId) ?? ''),
                );
            }
        }
    },
    { deep: true, immediate: true },
);

// ---- bulk ----

const bulk = ref<{ price: number | null; salePrice: number | null; stock: number | null }>({
    price: null,
    salePrice: null,
    stock: null,
});

function applyBulk(): void {
    for (const row of rows.value) {
        if (bulk.value.price !== null) row.price = bulk.value.price;
        if (bulk.value.salePrice !== null) row.salePrice = bulk.value.salePrice;
        if (bulk.value.stock !== null) row.stock = bulk.value.stock;
    }
}

// ---- sale schedules ----

const scheduleOpen = ref<Set<string>>(new Set());

function toggleSchedule(key: string): void {
    const next = new Set(scheduleOpen.value);
    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }
    scheduleOpen.value = next;
}

const cellInput =
    'h-10 w-full min-w-0 rounded-lg border border-line-strong bg-surface px-2.5 text-sm text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft';
</script>

<template>
    <div class="flex flex-col gap-5">
        <div class="flex flex-col gap-2">
            <span class="text-sm font-semibold">{{ t('admin.products.variants.type') }}</span>
            <div class="sm:w-96">
                <SegmentedControl
                    v-model="typeChoice"
                    :label="t('admin.products.variants.type')"
                    :options="typeOptions"
                />
            </div>
            <p class="text-[13px] leading-relaxed text-fg-muted">{{ t(`admin.products.variants.${type}Hint`) }}</p>
        </div>
        <p v-if="errors.variants?.[0] ?? errors.option_ids?.[0]" role="alert" class="text-[13px] text-danger">
            {{ errors.variants?.[0] ?? errors.option_ids?.[0] }}
        </p>

        <!-- Simple product: its single variant -->
        <div v-if="type === 'simple' && rows[0]" class="grid gap-4 sm:grid-cols-2">
            <div>
                <label :for="`${id}-sku`" class="mb-2 block text-sm font-semibold">{{
                    t('admin.products.variants.sku')
                }}</label>
                <input
                    :id="`${id}-sku`"
                    :value="rows[0].sku"
                    dir="ltr"
                    class="h-13 w-full rounded-xl border border-line-strong bg-surface px-3.5 text-[17px] text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft"
                    @input="((rows[0].sku = ($event.target as HTMLInputElement).value), (rows[0].skuEdited = true))"
                />
                <p v-if="error(0, 'sku')" class="mt-2 text-[13px] text-danger">{{ error(0, 'sku') }}</p>
            </div>
            <NumberField
                v-model="rows[0].price"
                :label="t('admin.products.variants.price')"
                :unit="unit"
                :factor="rialsPerUnit"
                :error="error(0, 'price')"
            />
            <NumberField
                v-model="rows[0].salePrice"
                :label="t('admin.products.variants.salePrice')"
                :unit="unit"
                :factor="rialsPerUnit"
                optional
                :error="error(0, 'sale_price')"
                :hint="t('admin.products.variants.salePriceHint')"
            />
            <NumberField
                v-if="trackStock"
                v-model="rows[0].stock"
                :label="t('admin.products.variants.stock')"
                :min="-1000000"
                :error="error(0, 'stock_quantity')"
            />
            <template v-if="rows[0].salePrice !== null">
                <div>
                    <label :for="`${id}-from`" class="mb-2 block text-sm font-semibold">
                        {{ t('admin.products.variants.saleStarts') }}
                        <span class="font-normal text-fg-muted">{{ t('common.optional') }}</span>
                    </label>
                    <input
                        :id="`${id}-from`"
                        v-model="rows[0].saleStartsAt"
                        type="datetime-local"
                        :class="[cellInput, 'h-13 rounded-xl']"
                    />
                </div>
                <div>
                    <label :for="`${id}-to`" class="mb-2 block text-sm font-semibold">
                        {{ t('admin.products.variants.saleEnds') }}
                        <span class="font-normal text-fg-muted">{{ t('common.optional') }}</span>
                    </label>
                    <input
                        :id="`${id}-to`"
                        v-model="rows[0].saleEndsAt"
                        type="datetime-local"
                        :class="[cellInput, 'h-13 rounded-xl']"
                    />
                    <p v-if="error(0, 'sale_ends_at')" class="mt-2 text-[13px] text-danger">
                        {{ error(0, 'sale_ends_at') }}
                    </p>
                </div>
            </template>
        </div>

        <!-- Variable product: options, then the generated grid -->
        <template v-else-if="type === 'variable'">
            <div class="flex flex-col gap-3">
                <span class="text-sm font-semibold">{{ t('admin.products.variants.options') }}</span>
                <p v-if="options.length === 0" class="text-sm text-fg-muted">
                    {{ t('admin.products.variants.noOptions') }}
                    <RouterLink
                        :to="{ name: 'attributes', query: { tab: 'options' } }"
                        class="font-semibold text-primary hover:text-primary-hover"
                    >
                        {{ t('admin.products.variants.manageOptions') }}
                    </RouterLink>
                </p>

                <div
                    v-for="item in chosenOptions"
                    :key="item.id"
                    class="flex flex-col gap-2 rounded-xl border border-line p-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold">{{ item.name }}</span>
                        <button
                            type="button"
                            :aria-label="t('admin.products.variants.removeOption', { name: item.name })"
                            class="flex size-9 cursor-pointer items-center justify-center rounded-lg text-fg-muted hover:bg-danger-soft hover:text-danger focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                            @click="removeOption(item.id)"
                        >
                            <AppIcon name="close" :size="16" />
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-1.5" role="group" :aria-label="item.name">
                        <button
                            v-for="value in item.values"
                            :key="value.id"
                            type="button"
                            :aria-pressed="
                                chosen.find((entry) => entry.optionId === item.id)?.valueIds.includes(value.id)
                            "
                            :class="[
                                'inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-full border px-3.5 text-sm focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                                chosen.find((entry) => entry.optionId === item.id)?.valueIds.includes(value.id)
                                    ? 'border-primary bg-primary-soft font-semibold text-fg'
                                    : 'border-line-strong text-fg-muted hover:text-fg',
                            ]"
                            @click="toggleValue(item.id, value.id)"
                        >
                            <span
                                v-if="value.color_hex"
                                class="size-3.5 rounded-full border border-line-strong"
                                :style="{ backgroundColor: value.color_hex }"
                                aria-hidden="true"
                            />
                            {{ value.value }}
                        </button>
                    </div>
                </div>

                <div
                    v-if="available.length > 0 && chosen.length < MAX_OPTIONS"
                    class="flex flex-wrap items-center gap-2"
                >
                    <label :for="`${id}-option`" class="sr-only">{{ t('admin.products.variants.addOption') }}</label>
                    <select
                        :id="`${id}-option`"
                        v-model="toAdd"
                        class="h-11 min-w-0 grow cursor-pointer rounded-[10px] border border-line-strong bg-surface px-3 text-sm text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft sm:max-w-xs"
                    >
                        <option value="">{{ t('admin.products.variants.chooseOption') }}</option>
                        <option v-for="option in available" :key="option.id" :value="String(option.id)">
                            {{ option.name }}
                        </option>
                    </select>
                    <AppButton variant="secondary" size="md" :disabled="toAdd === ''" @click="addOption">
                        <AppIcon name="plus" :size="18" />
                        {{ t('admin.products.variants.addOption') }}
                    </AppButton>
                </div>
                <p v-if="comboCount > MAX_VARIANTS" role="alert" class="text-[13px] text-danger">
                    {{
                        t('admin.products.variants.tooMany', {
                            n: formatNumber(comboCount),
                            max: formatNumber(MAX_VARIANTS),
                        })
                    }}
                </p>
            </div>

            <div v-if="rows.length > 0 && chosen.length > 0" class="flex flex-col gap-3">
                <div class="flex flex-wrap items-end gap-2 rounded-xl bg-surface-muted p-3">
                    <span class="w-full text-xs font-semibold text-fg-muted">{{
                        t('admin.products.variants.bulk')
                    }}</span>
                    <div class="w-36">
                        <NumberField
                            v-model="bulk.price"
                            compact
                            :label="t('admin.products.variants.price')"
                            :placeholder="t('admin.products.variants.price')"
                            :unit="unit"
                            :factor="rialsPerUnit"
                        />
                    </div>
                    <div class="w-36">
                        <NumberField
                            v-model="bulk.salePrice"
                            compact
                            :label="t('admin.products.variants.salePrice')"
                            :placeholder="t('admin.products.variants.salePrice')"
                            :unit="unit"
                            :factor="rialsPerUnit"
                        />
                    </div>
                    <div v-if="trackStock" class="w-28">
                        <NumberField
                            v-model="bulk.stock"
                            compact
                            :label="t('admin.products.variants.stock')"
                            :placeholder="t('admin.products.variants.stock')"
                        />
                    </div>
                    <AppButton variant="secondary" size="md" @click="applyBulk">{{
                        t('admin.products.variants.applyAll')
                    }}</AppButton>
                </div>

                <div class="overflow-x-auto rounded-xl border border-line">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="bg-bg text-xs font-semibold text-fg-muted">
                            <tr>
                                <th scope="col" class="w-12 px-2 py-2.5">
                                    <span class="sr-only">{{ t('admin.products.variants.enabled') }}</span>
                                </th>
                                <th scope="col" class="px-2 py-2.5 text-start">
                                    {{ t('admin.products.variants.variant') }}
                                </th>
                                <th scope="col" class="px-2 py-2.5 text-start">
                                    {{ t('admin.products.variants.sku') }}
                                </th>
                                <th scope="col" class="px-2 py-2.5 text-start">
                                    {{ t('admin.products.variants.price') }} ({{ unit }})
                                </th>
                                <th scope="col" class="px-2 py-2.5 text-start">
                                    {{ t('admin.products.variants.salePrice') }}
                                </th>
                                <th v-if="trackStock" scope="col" class="w-24 px-2 py-2.5 text-start">
                                    {{ t('admin.products.variants.stock') }}
                                </th>
                                <th scope="col" class="px-2 py-2.5 text-start">
                                    {{ t('admin.products.variants.image') }}
                                </th>
                                <th scope="col" class="w-10 px-2 py-2.5">
                                    <span class="sr-only">{{ t('admin.products.variants.schedule') }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <template v-for="(row, index) in rows" :key="row.key">
                                <tr :class="!row.isEnabled && 'bg-surface-muted/50 text-fg-muted'">
                                    <td class="px-2 py-2 text-center">
                                        <input
                                            v-model="row.isEnabled"
                                            type="checkbox"
                                            class="size-4 cursor-pointer accent-[var(--color-primary)]"
                                            :aria-label="
                                                t('admin.products.variants.enableRow', {
                                                    name: rowLabel(row, chosenOptions),
                                                })
                                            "
                                        />
                                    </td>
                                    <th scope="row" class="px-2 py-2 text-start font-medium whitespace-nowrap">
                                        {{ rowLabel(row, chosenOptions) }}
                                    </th>
                                    <td class="px-2 py-2">
                                        <input
                                            :value="row.sku"
                                            dir="ltr"
                                            :aria-label="
                                                t('admin.products.variants.skuOf', {
                                                    name: rowLabel(row, chosenOptions),
                                                })
                                            "
                                            :class="[cellInput, error(index, 'sku') && 'border-2 border-danger']"
                                            @input="
                                                ((row.sku = ($event.target as HTMLInputElement).value),
                                                (row.skuEdited = true))
                                            "
                                        />
                                        <p v-if="error(index, 'sku')" class="mt-1 text-xs text-danger">
                                            {{ error(index, 'sku') }}
                                        </p>
                                    </td>
                                    <td class="px-2 py-2">
                                        <NumberField
                                            v-model="row.price"
                                            compact
                                            :label="
                                                t('admin.products.variants.priceOf', {
                                                    name: rowLabel(row, chosenOptions),
                                                })
                                            "
                                            :factor="rialsPerUnit"
                                            :error="error(index, 'price')"
                                        />
                                    </td>
                                    <td class="px-2 py-2">
                                        <NumberField
                                            v-model="row.salePrice"
                                            compact
                                            :label="
                                                t('admin.products.variants.salePriceOf', {
                                                    name: rowLabel(row, chosenOptions),
                                                })
                                            "
                                            :factor="rialsPerUnit"
                                            :error="error(index, 'sale_price')"
                                        />
                                    </td>
                                    <td v-if="trackStock" class="px-2 py-2">
                                        <NumberField
                                            v-model="row.stock"
                                            compact
                                            :min="-1000000"
                                            :label="
                                                t('admin.products.variants.stockOf', {
                                                    name: rowLabel(row, chosenOptions),
                                                })
                                            "
                                            :error="error(index, 'stock_quantity')"
                                        />
                                    </td>
                                    <td class="px-2 py-2">
                                        <select
                                            :value="row.imageId === null ? '' : String(row.imageId)"
                                            :aria-label="
                                                t('admin.products.variants.imageOf', {
                                                    name: rowLabel(row, chosenOptions),
                                                })
                                            "
                                            :disabled="images.length === 0"
                                            :class="[cellInput, 'cursor-pointer']"
                                            @change="
                                                row.imageId =
                                                    ($event.target as HTMLSelectElement).value === ''
                                                        ? null
                                                        : Number(($event.target as HTMLSelectElement).value)
                                            "
                                        >
                                            <option value="">{{ t('admin.products.variants.mainImage') }}</option>
                                            <option
                                                v-for="(image, imageIndex) in images"
                                                :key="image.id"
                                                :value="String(image.id)"
                                            >
                                                {{
                                                    t('admin.products.variants.imageN', {
                                                        n: formatNumber(imageIndex + 1),
                                                    })
                                                }}
                                            </option>
                                        </select>
                                    </td>
                                    <td class="px-2 py-2">
                                        <button
                                            type="button"
                                            :aria-expanded="scheduleOpen.has(row.key)"
                                            :aria-label="
                                                t('admin.products.variants.scheduleOf', {
                                                    name: rowLabel(row, chosenOptions),
                                                })
                                            "
                                            :title="t('admin.products.variants.schedule')"
                                            :disabled="row.salePrice === null"
                                            class="flex size-9 cursor-pointer items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                                            @click="toggleSchedule(row.key)"
                                        >
                                            <AppIcon name="clock" :size="16" />
                                        </button>
                                    </td>
                                </tr>
                                <tr
                                    v-if="scheduleOpen.has(row.key) && row.salePrice !== null"
                                    class="bg-surface-muted/40"
                                >
                                    <td />
                                    <td colspan="7" class="px-2 py-2">
                                        <div class="flex flex-wrap items-center gap-2 text-xs text-fg-muted">
                                            <span>{{ t('admin.products.variants.saleStarts') }}</span>
                                            <input
                                                v-model="row.saleStartsAt"
                                                type="datetime-local"
                                                :class="[cellInput, 'w-auto']"
                                            />
                                            <span>{{ t('admin.products.variants.saleEnds') }}</span>
                                            <input
                                                v-model="row.saleEndsAt"
                                                type="datetime-local"
                                                :class="[cellInput, 'w-auto']"
                                            />
                                            <span v-if="error(index, 'sale_ends_at')" class="text-danger">{{
                                                error(index, 'sale_ends_at')
                                            }}</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="error(index, 'option_value_ids')">
                                    <td colspan="8" class="px-2 pb-2 text-xs text-danger">
                                        {{ error(index, 'option_value_ids') }}
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p class="text-[13px] text-fg-muted">
                    {{ t('admin.products.variants.gridHint', { n: formatNumber(rows.length) }) }}
                </p>
            </div>
        </template>
    </div>
</template>
