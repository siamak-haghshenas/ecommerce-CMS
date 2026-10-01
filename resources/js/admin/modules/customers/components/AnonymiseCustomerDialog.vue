<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApiForm } from '@/shared/composables/useApiForm';
import { useLocale } from '@/shared/composables/useLocale';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import TextField from '@/shared/ui/TextField.vue';
import { onlyLatinDigits } from '@/shared/utils/digits';
import { customersApi } from '../api';
import type { Customer } from '../types';

const props = defineProps<{ open: boolean; customer: Customer | null }>();
const emit = defineEmits<{ close: []; done: [customer: Customer] }>();

const { t } = useI18n();
const { localizeDigits } = useLocale();
const form = useApiForm([] as const);
const typedPhone = ref('');

watch(
    () => props.open,
    (open) => {
        if (open) {
            form.clearErrors();
            typedPhone.value = '';
        }
    },
);

/** Irreversible, so the button only unlocks once the customer's number is typed (any digit script). */
const confirmed = computed(
    () => props.customer?.phone_number != null && onlyLatinDigits(typedPhone.value) === props.customer.phone_number,
);

async function anonymise(): Promise<void> {
    if (!props.customer || !confirmed.value) {
        return;
    }

    const updated = await form.submit(() => customersApi.anonymise(props.customer!.id));

    if (updated) {
        emit('done', updated);
    }
}
</script>

<template>
    <AppDialog
        :open="open"
        :title="t('admin.customers.anonymiseTitle')"
        icon="eraser"
        tone="danger"
        :busy="form.processing.value"
        @close="emit('close')"
    >
        <FormAlert v-if="form.generalError.value" :message="form.generalError.value" />
        <p class="text-sm leading-loose">{{ t('admin.customers.anonymiseIntro') }}</p>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="flex flex-col gap-1.5 rounded-xl bg-danger-soft p-3.5">
                <span class="text-[13px] font-bold text-danger">{{ t('admin.customers.anonymiseRemoved') }}</span>
                <span class="text-[13px] leading-relaxed">{{ t('admin.customers.anonymiseRemovedItems') }}</span>
            </div>
            <div class="flex flex-col gap-1.5 rounded-xl bg-surface-muted p-3.5">
                <span class="text-[13px] font-bold">{{ t('admin.customers.anonymiseKept') }}</span>
                <span class="text-[13px] leading-relaxed">{{ t('admin.customers.anonymiseKeptItems') }}</span>
            </div>
        </div>
        <form id="anonymise-customer" novalidate @submit.prevent="anonymise">
            <TextField
                v-model="typedPhone"
                :label="t('admin.customers.anonymiseConfirmLabel')"
                type="tel"
                inputmode="tel"
                dir="ltr"
                autocomplete="off"
                :placeholder="customer?.phone_number ? localizeDigits(customer.phone_number) : ''"
            />
        </form>
        <p class="flex items-center gap-2 text-[13px] text-fg-muted">
            <AppIcon name="shieldCheck" :size="16" />
            {{ t('admin.customers.anonymiseSuperAdminOnly') }}
        </p>
        <template #footer>
            <AppButton
                type="submit"
                form="anonymise-customer"
                variant="danger"
                size="md"
                :disabled="!confirmed"
                :loading="form.processing.value"
            >
                <AppIcon name="eraser" :size="18" />
                {{ t('admin.customers.anonymiseConfirm') }}
            </AppButton>
            <AppButton variant="secondary" size="md" :disabled="form.processing.value" @click="emit('close')">
                {{ t('common.cancel') }}
            </AppButton>
        </template>
    </AppDialog>
</template>
