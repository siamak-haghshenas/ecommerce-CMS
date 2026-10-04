<script setup lang="ts">
import { ref, watch } from 'vue';
import { useLocale } from '@/shared/composables/useLocale';
import { toLatinDigits } from '@/shared/utils/digits';

/**
 * A whole number that reads like plain text until hovered or focused (the bulk editor's cells).
 * Typed with any digits, grouped when not editing; `factor` converts shown ↔ stored (Toman ↔ Rial).
 */
const props = withDefaults(
    defineProps<{
        modelValue: number | null;
        label: string;
        factor?: number;
        suffix?: string;
        placeholder?: string;
        invalid?: boolean;
        changed?: boolean;
        allowNegative?: boolean;
    }>(),
    { factor: 1, invalid: false, changed: false, allowNegative: false },
);

const emit = defineEmits<{ 'update:modelValue': [value: number | null] }>();

const { formatNumber } = useLocale();
const focused = ref(false);
const text = ref('');

const display = (value: number | null, grouped: boolean): string =>
    value === null ? '' : grouped ? formatNumber(value / props.factor) : String(value / props.factor);

watch(
    () => [props.modelValue, props.factor],
    () => {
        if (!focused.value) {
            text.value = display(props.modelValue, true);
        }
    },
    { immediate: true },
);

function onInput(value: string): void {
    text.value = value;
    const digits = toLatinDigits(value).replace(/[,٬،\s]/g, '');

    if (digits === '') {
        emit('update:modelValue', null);
    } else if ((props.allowNegative ? /^-?\d+$/ : /^\d+$/).test(digits)) {
        emit('update:modelValue', Number(digits) * props.factor);
    }
}

function onFocus(event: FocusEvent): void {
    focused.value = true;
    text.value = display(props.modelValue, false);
    (event.target as HTMLInputElement).select();
}

function onBlur(): void {
    focused.value = false;
    text.value = display(props.modelValue, true);
}
</script>

<template>
    <div
        :class="[
            'flex h-10 items-center gap-1.5 rounded-lg border px-2.5 transition-colors motion-reduce:transition-none',
            'focus-within:border-primary focus-within:bg-surface focus-within:ring-4 focus-within:ring-primary-soft',
            invalid
                ? 'border-danger bg-danger-soft'
                : changed
                  ? 'border-warning/60 bg-warning-soft'
                  : 'border-transparent hover:border-line-strong hover:bg-surface',
        ]"
    >
        <input
            :value="text"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            :aria-label="label"
            :aria-invalid="invalid || undefined"
            :placeholder="placeholder"
            class="h-full w-full min-w-0 bg-transparent text-sm font-medium text-fg tabular-nums outline-none placeholder:font-normal placeholder:text-fg-muted"
            @input="onInput(($event.target as HTMLInputElement).value)"
            @focus="onFocus"
            @blur="onBlur"
        />
        <span v-if="suffix" class="hidden shrink-0 text-xs text-fg-muted sm:inline">{{ suffix }}</span>
    </div>
</template>
