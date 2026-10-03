<script setup lang="ts">
import { computed, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '../composables/useLocale';
import AppIcon from './AppIcon.vue';

const props = withDefaults(
    defineProps<{
        label: string;
        error?: string;
        hint?: string;
        optional?: boolean;
        rows?: number;
        /** Shows a live character counter. */
        maxlength?: number;
    }>(),
    { optional: false, rows: 3 },
);

const model = defineModel<string>({ required: true });

const { t } = useI18n();
const { formatNumber } = useLocale();
const id = useId();

const describedBy = computed(
    () => [props.error && `${id}-error`, props.hint && `${id}-hint`].filter(Boolean).join(' ') || undefined,
);
</script>

<template>
    <div>
        <div class="mb-2 flex items-center justify-between gap-3">
            <label :for="id" class="text-sm font-semibold text-fg">
                {{ label }}
                <span v-if="optional" class="font-normal text-fg-muted">{{ t('common.optional') }}</span>
            </label>
            <span v-if="maxlength" class="text-xs text-fg-muted" aria-hidden="true">
                {{ formatNumber(model.length) }} / {{ formatNumber(maxlength) }}
            </span>
        </div>
        <textarea
            :id="id"
            v-model="model"
            dir="auto"
            :rows="rows"
            :maxlength="maxlength"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="describedBy"
            :class="[
                'block w-full resize-y rounded-xl border bg-surface px-3.5 py-3 text-[15px] leading-relaxed text-fg transition-shadow duration-150 outline-none placeholder:text-fg-muted',
                error
                    ? 'border-2 border-danger'
                    : 'border-line-strong focus:border-2 focus:border-primary focus:ring-4 focus:ring-primary-soft',
            ]"
        />
        <p
            v-if="error"
            :id="`${id}-error`"
            class="mt-2 flex items-start gap-1.5 text-[13px] leading-relaxed text-danger"
        >
            <AppIcon name="alert" :size="16" class="mt-0.5" />
            <span>{{ error }}</span>
        </p>
        <p v-if="hint" :id="`${id}-hint`" class="mt-2 text-[13px] leading-relaxed text-fg-muted">{{ hint }}</p>
    </div>
</template>
