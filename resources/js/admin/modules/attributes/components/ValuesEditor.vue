<script setup lang="ts">
import { useId } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { useI18n } from 'vue-i18n';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import { slugify } from '../../seo/analysis';
import { valueRow, type ValueRow } from '../values';

/**
 * The ordered values of an attribute or option: drag to reorder, the slug is suggested from
 * the value (Persian is transliterated), colors for color options. Errors come from the API
 * as `values.{index}.{field}`.
 */
const props = withDefaults(
    defineProps<{ label: string; withColor?: boolean; errors?: Record<string, string[]>; disabled?: boolean }>(),
    { withColor: false, errors: () => ({}), disabled: false },
);
const rows = defineModel<ValueRow[]>({ required: true });

const { t } = useI18n();
const id = useId();

function error(index: number): string | undefined {
    return ['value', 'slug', 'color_hex']
        .map((field) => props.errors[`values.${index}.${field}`]?.[0])
        .find((message) => message !== undefined);
}

function onValueInput(row: ValueRow, value: string): void {
    row.value = value;

    if (!row.slugEdited) {
        row.slug = slugify(value);
    }
}

function onSlugInput(row: ValueRow, slug: string): void {
    row.slug = slug;
    row.slugEdited = true;
}

function add(): void {
    rows.value = [...rows.value, valueRow()];
}

function remove(key: string): void {
    rows.value = rows.value.filter((row) => row.key !== key);
}

const inputClass =
    'h-11 min-w-0 rounded-[10px] border border-line-strong bg-surface px-3 text-sm text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft';
</script>

<template>
    <fieldset class="flex flex-col gap-2.5" :aria-describedby="`${id}-hint`">
        <legend class="mb-1 text-sm font-semibold text-fg">{{ label }}</legend>
        <p :id="`${id}-hint`" class="text-[13px] leading-relaxed text-fg-muted">
            {{ t('admin.attributes.valuesHint') }}
        </p>
        <p v-if="errors.values?.[0]" role="alert" class="text-[13px] text-danger">{{ errors.values[0] }}</p>

        <VueDraggable
            v-model="rows"
            tag="ol"
            handle=".value-handle"
            :animation="150"
            :disabled="disabled"
            class="flex flex-col gap-2"
        >
            <li v-for="(row, index) in rows" :key="row.key" class="flex flex-col gap-1">
                <div class="flex items-center gap-2">
                    <span
                        class="value-handle hidden size-8 shrink-0 cursor-grab items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted lg:flex"
                        aria-hidden="true"
                    >
                        <AppIcon name="grip" :size="16" />
                    </span>
                    <input
                        v-if="withColor"
                        v-model="row.colorHex"
                        type="color"
                        :aria-label="t('admin.attributes.valueColor', { n: index + 1 })"
                        class="h-11 w-12 shrink-0 cursor-pointer rounded-[10px] border border-line-strong bg-surface p-1"
                    />
                    <input
                        :value="row.value"
                        dir="auto"
                        :aria-label="t('admin.attributes.valueText', { n: index + 1 })"
                        :placeholder="t('admin.attributes.valuePlaceholder')"
                        :class="[inputClass, 'grow basis-0']"
                        @input="onValueInput(row, ($event.target as HTMLInputElement).value)"
                    />
                    <input
                        :value="row.slug"
                        dir="ltr"
                        :aria-label="t('admin.attributes.valueSlug', { n: index + 1 })"
                        placeholder="slug"
                        :class="[inputClass, 'w-28 shrink-0 sm:w-36']"
                        @input="onSlugInput(row, ($event.target as HTMLInputElement).value)"
                    />
                    <button
                        type="button"
                        :aria-label="t('admin.attributes.removeValue', { n: index + 1 })"
                        class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-[10px] text-fg-muted hover:bg-danger-soft hover:text-danger focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                        @click="remove(row.key)"
                    >
                        <AppIcon name="trash" :size="18" />
                    </button>
                </div>
                <p v-if="error(index)" class="ps-2 text-[13px] text-danger lg:ps-10">{{ error(index) }}</p>
            </li>
        </VueDraggable>

        <div>
            <AppButton variant="secondary" size="md" @click="add">
                <AppIcon name="plus" :size="18" />
                {{ t('admin.attributes.addValue') }}
            </AppButton>
        </div>
    </fieldset>
</template>
