<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import AppIcon, { type IconName } from './AppIcon.vue';

const props = withDefaults(
    defineProps<{
        label: string;
        type?: 'text' | 'email' | 'tel' | 'password';
        icon?: IconName;
        error?: string;
        hint?: string;
        optional?: boolean;
        autocomplete?: string;
        inputmode?: 'text' | 'email' | 'tel' | 'numeric';
        dir?: 'ltr' | 'rtl' | 'auto';
        placeholder?: string;
        autofocus?: boolean;
    }>(),
    { type: 'text', dir: 'auto', optional: false, autofocus: false },
);

const model = defineModel<string>({ required: true });

const { t } = useI18n();
const id = useId();
const passwordVisible = ref(false);

const inputType = computed(() => (props.type === 'password' && passwordVisible.value ? 'text' : props.type));
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
            <slot name="label-action" />
        </div>

        <div
            :dir="dir"
            :class="[
                'flex h-13 items-center gap-2.5 rounded-xl border bg-surface ps-3.5 transition-shadow duration-150',
                type === 'password' ? 'pe-1.5' : 'pe-3.5',
                error
                    ? 'border-2 border-danger text-danger'
                    : 'border-line-strong text-icon focus-within:border-2 focus-within:border-primary focus-within:ring-4 focus-within:ring-primary-soft',
            ]"
        >
            <AppIcon v-if="icon" :name="icon" />
            <input
                :id="id"
                v-model="model"
                :type="inputType"
                :autocomplete="autocomplete"
                :inputmode="inputmode"
                :placeholder="placeholder"
                :autofocus="autofocus"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="describedBy"
                class="h-full min-w-0 grow bg-transparent text-[17px] text-fg outline-none placeholder:text-fg-muted/70"
            />
            <button
                v-if="type === 'password'"
                type="button"
                :aria-label="passwordVisible ? t('common.hidePassword') : t('common.showPassword')"
                :aria-pressed="passwordVisible"
                class="flex size-11 cursor-pointer items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                @click="passwordVisible = !passwordVisible"
            >
                <AppIcon :name="passwordVisible ? 'eyeOff' : 'eye'" />
            </button>
        </div>

        <p
            v-if="error"
            :id="`${id}-error`"
            class="mt-2 flex items-start gap-1.5 text-[13px] leading-relaxed text-danger"
        >
            <AppIcon name="alert" :size="16" class="mt-0.5" />
            <span>{{ error }}</span>
        </p>
        <p v-if="hint" :id="`${id}-hint`" class="mt-2 text-[13px] leading-relaxed text-fg-muted">{{ hint }}</p>
        <slot name="below" />
    </div>
</template>
