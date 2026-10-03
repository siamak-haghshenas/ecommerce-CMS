<script setup lang="ts">
import { useId } from 'vue';
import { useI18n } from 'vue-i18n';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import type { Attribute } from '../../attributes/types';

/** One specification value being edited; which field is used depends on the attribute type. */
export interface SpecificationInput {
    valueIds: number[];
    text: string;
    number: string;
    boolean: boolean | null;
}

/**
 * The specifications of the product's categories (fetched by the editor when categories
 * change). Values for attributes no longer applicable are simply not sent.
 */
/** `errors` are keyed by attribute id. The editor creates an entry for every attribute before rendering. */
defineProps<{ attributes: Attribute[]; loading: boolean; errors: Record<number, string> }>();
const values = defineModel<Record<number, SpecificationInput>>({ required: true });

const { t } = useI18n();
const id = useId();

function entry(attributeId: number): SpecificationInput {
    values.value[attributeId] ??= { valueIds: [], text: '', number: '', boolean: null };

    return values.value[attributeId];
}

function toggleValue(attributeId: number, valueId: number): void {
    const current = entry(attributeId);
    current.valueIds = current.valueIds.includes(valueId)
        ? current.valueIds.filter((item) => item !== valueId)
        : [...current.valueIds, valueId];
}

const inputClass =
    'h-11 w-full rounded-[10px] border border-line-strong bg-surface px-3 text-sm text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft';
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-if="loading" class="flex justify-center py-4"><AppSpinner /></div>
        <p v-else-if="attributes.length === 0" class="text-sm text-fg-muted">{{ t('admin.products.specsNone') }}</p>

        <div v-for="attribute in attributes" v-else :key="attribute.id" class="flex flex-col gap-2">
            <span :id="`${id}-${attribute.id}`" class="text-sm font-semibold">
                {{ attribute.name }}
                <span v-if="attribute.unit" class="font-normal text-fg-muted">({{ attribute.unit }})</span>
            </span>

            <div
                v-if="attribute.type === 'select'"
                role="group"
                :aria-labelledby="`${id}-${attribute.id}`"
                class="flex flex-wrap gap-1.5"
            >
                <button
                    v-for="value in attribute.values ?? []"
                    :key="value.id"
                    type="button"
                    :aria-pressed="entry(attribute.id).valueIds.includes(value.id)"
                    :class="[
                        'h-9 cursor-pointer rounded-full border px-3.5 text-sm focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                        entry(attribute.id).valueIds.includes(value.id)
                            ? 'border-primary bg-primary-soft font-semibold text-fg'
                            : 'border-line-strong text-fg-muted hover:text-fg',
                    ]"
                    @click="toggleValue(attribute.id, value.id)"
                >
                    {{ value.value }}
                </button>
            </div>

            <input
                v-else-if="attribute.type === 'text'"
                v-model="entry(attribute.id).text"
                dir="auto"
                :aria-labelledby="`${id}-${attribute.id}`"
                :class="inputClass"
            />

            <input
                v-else-if="attribute.type === 'number'"
                v-model="entry(attribute.id).number"
                dir="ltr"
                inputmode="decimal"
                :aria-labelledby="`${id}-${attribute.id}`"
                :class="[inputClass, 'max-w-48']"
            />

            <div v-else role="radiogroup" :aria-labelledby="`${id}-${attribute.id}`" class="flex gap-1.5">
                <button
                    v-for="option in [
                        { value: true, label: t('admin.products.yes') },
                        { value: false, label: t('admin.products.no') },
                        { value: null, label: t('admin.products.notSet') },
                    ]"
                    :key="String(option.value)"
                    type="button"
                    role="radio"
                    :aria-checked="entry(attribute.id).boolean === option.value"
                    :class="[
                        'h-9 cursor-pointer rounded-full border px-3.5 text-sm focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                        entry(attribute.id).boolean === option.value
                            ? 'border-primary bg-primary-soft font-semibold'
                            : 'border-line-strong text-fg-muted hover:text-fg',
                    ]"
                    @click="entry(attribute.id).boolean = option.value"
                >
                    {{ option.label }}
                </button>
            </div>

            <p v-if="errors[attribute.id]" class="text-[13px] text-danger">{{ errors[attribute.id] }}</p>
        </div>
    </div>
</template>
