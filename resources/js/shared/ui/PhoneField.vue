<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '../composables/useLocale';
import { toLatinDigits } from '../utils/digits';
import TextField from './TextField.vue';

defineProps<{ error?: string; hint?: string; autocomplete?: string; autofocus?: boolean }>();

/** Always holds Latin digits; Persian digits are only a display concern. */
const model = defineModel<string>({ required: true });

const { t } = useI18n();
const { localizeDigits } = useLocale();

const displayValue = computed({
    get: () => localizeDigits(model.value),
    set: (value: string) => {
        model.value = toLatinDigits(value);
    },
});
</script>

<template>
    <TextField
        v-model="displayValue"
        :label="t('auth.fields.phone')"
        type="tel"
        inputmode="tel"
        dir="ltr"
        icon="phone"
        :autocomplete="autocomplete ?? 'tel'"
        :autofocus="autofocus"
        :error="error"
        :hint="hint"
        :placeholder="localizeDigits('09123456789')"
    />
</template>
