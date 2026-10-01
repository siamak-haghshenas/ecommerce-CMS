<script setup lang="ts">
import { computed, nextTick, onMounted, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '../composables/useLocale';
import { onlyLatinDigits } from '../utils/digits';
import AppIcon from './AppIcon.vue';

const props = withDefaults(
    defineProps<{ length?: number; error?: string; disabled?: boolean; autofocus?: boolean }>(),
    { length: 5, disabled: false, autofocus: true },
);

const emit = defineEmits<{ complete: [code: string] }>();

/** The code in Latin digits, at most `length` characters. */
const model = defineModel<string>({ required: true });

const { t } = useI18n();
const { localizeDigits } = useLocale();
const id = useId();
const inputs = ref<HTMLInputElement[]>([]);

const digits = computed(() => Array.from({ length: props.length }, (_, index) => model.value[index] ?? ''));

function focusBox(index: number): void {
    const box = inputs.value[Math.max(0, Math.min(index, props.length - 1))];
    box?.focus();
    box?.select();
}

function setCode(code: string, focusIndex: number): void {
    // Check the new value itself: a parent-controlled model only reflects it after the parent re-renders.
    const next = code.slice(0, props.length);
    model.value = next;
    void nextTick(() => focusBox(focusIndex));

    if (next.length === props.length) {
        emit('complete', next);
    }
}

function onInput(index: number, event: Event): void {
    const typed = onlyLatinDigits((event.target as HTMLInputElement).value);

    if (typed === '') {
        (event.target as HTMLInputElement).value = localizeDigits(digits.value[index] ?? '');
        return;
    }

    // Several digits at once (SMS autofill or typing fast) fill the following boxes.
    const code = (model.value.slice(0, index) + typed).slice(0, props.length);
    setCode(code, index + typed.length);
}

function onKeydown(index: number, event: KeyboardEvent): void {
    if (event.key === 'Backspace') {
        event.preventDefault();
        const removeAt = digits.value[index] === '' ? index - 1 : index;

        if (removeAt >= 0) {
            model.value = model.value.slice(0, removeAt);
            focusBox(removeAt);
        }
    } else if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        // The boxes are always left-to-right, whatever the page direction.
        event.preventDefault();
        focusBox(index + (event.key === 'ArrowRight' ? 1 : -1));
    }
}

function onPaste(event: ClipboardEvent): void {
    const pasted = onlyLatinDigits(event.clipboardData?.getData('text') ?? '');

    if (pasted !== '') {
        event.preventDefault();
        setCode(pasted, pasted.length);
    }
}

/** Parents call this after a wrong code to start over. */
function reset(): void {
    model.value = '';
    void nextTick(() => focusBox(0));
}

watch(
    () => props.disabled,
    (disabled) => {
        if (!disabled && model.value.length < props.length) {
            void nextTick(() => focusBox(model.value.length));
        }
    },
);

onMounted(() => {
    if (props.autofocus) {
        focusBox(model.value.length);
    }
});

defineExpose({ reset });
</script>

<template>
    <fieldset :aria-describedby="error ? `${id}-error` : undefined">
        <legend class="sr-only">{{ t('auth.fields.code') }}</legend>
        <div dir="ltr" class="grid gap-2.5" :style="{ gridTemplateColumns: `repeat(${length}, minmax(0, 1fr))` }">
            <input
                v-for="(digit, index) in digits"
                :key="index"
                ref="inputs"
                :value="localizeDigits(digit)"
                :aria-label="
                    t('auth.fields.codeDigit', { n: localizeDigits(index + 1), total: localizeDigits(length) })
                "
                :aria-invalid="error ? 'true' : undefined"
                :autocomplete="index === 0 ? 'one-time-code' : 'off'"
                :disabled="disabled"
                inputmode="numeric"
                type="text"
                :class="[
                    'h-15 w-full min-w-0 rounded-xl border bg-surface text-center text-2xl font-semibold text-fg transition-shadow duration-150 outline-none',
                    'focus:border-2 focus:border-primary focus:ring-4 focus:ring-primary-soft disabled:opacity-60',
                    error ? 'border-2 border-danger' : 'border-line-strong',
                ]"
                @input="onInput(index, $event)"
                @keydown="onKeydown(index, $event)"
                @paste="onPaste"
                @focus="($event.target as HTMLInputElement).select()"
            />
        </div>
        <p
            v-if="error"
            :id="`${id}-error`"
            class="mt-3 flex items-start gap-1.5 text-[13px] leading-relaxed text-danger"
        >
            <AppIcon name="alert" :size="16" class="mt-0.5" />
            <span>{{ error }}</span>
        </p>
    </fieldset>
</template>
