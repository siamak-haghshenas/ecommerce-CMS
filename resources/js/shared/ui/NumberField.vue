<script setup lang="ts">
import { computed, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '../composables/useLocale';
import { toLatinDigits } from '../utils/digits';
import AppIcon from './AppIcon.vue';

/**
 * A whole number typed with any digits (۱۲۳ or 123), shown with thousands separators when not
 * editing. `factor` converts between what is shown and what is stored, e.g. prices shown in
 * Toman (factor 10) are stored in Rials. `compact` drops the label for table cells.
 */
const props = withDefaults(
    defineProps<{
        label: string;
        unit?: string;
        factor?: number;
        min?: number;
        error?: string;
        hint?: string;
        optional?: boolean;
        compact?: boolean;
        placeholder?: string;
        disabled?: boolean;
    }>(),
    { factor: 1, min: 0, optional: false, compact: false, disabled: false },
);

const model = defineModel<number | null>({ required: true });

const { t } = useI18n();
const { formatNumber } = useLocale();
const id = useId();
const focused = ref(false);
const text = ref('');

function display(value: number | null, grouped: boolean): string {
    if (value === null) {
        return '';
    }

    const shown = value / props.factor;

    return grouped ? formatNumber(shown) : String(shown);
}

watch(
    [model, () => props.factor],
    () => {
        if (!focused.value) {
            text.value = display(model.value, true);
        }
    },
    { immediate: true },
);

function onInput(value: string): void {
    text.value = value;
    const digits = toLatinDigits(value).replace(/[,٬،\s]/g, '');

    if (digits === '') {
        model.value = null;
    } else if (/^-?\d+$/.test(digits)) {
        model.value = Number(digits) * props.factor;
    }
}

function onFocus(): void {
    focused.value = true;
    text.value = display(model.value, false);
}

function onBlur(): void {
    focused.value = false;
    text.value = display(model.value, true);
}

const describedBy = computed(
    () => [props.error && `${id}-error`, props.hint && `${id}-hint`].filter(Boolean).join(' ') || undefined,
);
</script>

<template>
    <div>
        <label v-if="!compact" :for="id" class="mb-2 block text-sm font-semibold text-fg">
            {{ label }}
            <span v-if="optional" class="font-normal text-fg-muted">{{ t('common.optional') }}</span>
        </label>
        <div
            :class="[
                'flex items-center gap-2 border bg-surface px-3 transition-shadow',
                compact ? 'h-10 rounded-lg' : 'h-13 rounded-xl',
                disabled && 'opacity-60',
                error
                    ? 'border-2 border-danger'
                    : 'border-line-strong focus-within:border-primary focus-within:ring-4 focus-within:ring-primary-soft',
            ]"
        >
            <input
                :id="id"
                :value="text"
                type="text"
                inputmode="numeric"
                dir="ltr"
                autocomplete="off"
                :disabled="disabled"
                :placeholder="placeholder"
                :aria-label="compact ? label : undefined"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="describedBy"
                :class="[
                    'h-full w-full min-w-0 bg-transparent text-fg outline-none',
                    compact ? 'text-sm' : 'text-[17px]',
                ]"
                @input="onInput(($event.target as HTMLInputElement).value)"
                @focus="onFocus"
                @blur="onBlur"
            />
            <span v-if="unit" class="shrink-0 text-xs text-fg-muted">{{ unit }}</span>
        </div>
        <p
            v-if="error && !compact"
            :id="`${id}-error`"
            class="mt-2 flex items-start gap-1.5 text-[13px] leading-relaxed text-danger"
        >
            <AppIcon name="alert" :size="16" class="mt-0.5" />
            <span>{{ error }}</span>
        </p>
        <p v-else-if="error" :id="`${id}-error`" class="mt-1 text-xs text-danger">{{ error }}</p>
        <p v-if="hint && !compact" :id="`${id}-hint`" class="mt-2 text-[13px] leading-relaxed text-fg-muted">
            {{ hint }}
        </p>
    </div>
</template>
