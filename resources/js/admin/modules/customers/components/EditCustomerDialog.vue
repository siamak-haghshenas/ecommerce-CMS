<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApiForm } from '@/shared/composables/useApiForm';
import { useLocale } from '@/shared/composables/useLocale';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import TextField from '@/shared/ui/TextField.vue';
import { customersApi } from '../api';
import type { Customer } from '../types';

const props = defineProps<{ open: boolean; customer: Customer | null }>();
const emit = defineEmits<{ close: []; saved: [customer: Customer] }>();

const { t } = useI18n();
const { localizeDigits } = useLocale();
const form = useApiForm(['name', 'email', 'locale'] as const);
const fields = reactive({ name: '', email: '', locale: 'fa' as 'fa' | 'en' });

const languages = computed(() => [
    { value: 'fa' as const, label: t('admin.customers.languageFa') },
    { value: 'en' as const, label: t('admin.customers.languageEn') },
]);

watch(
    () => props.open,
    (open) => {
        if (open && props.customer) {
            form.clearErrors();
            fields.name = props.customer.name;
            fields.email = props.customer.email ?? '';
            fields.locale = props.customer.locale;
        }
    },
);

async function save(): Promise<void> {
    if (!props.customer) {
        return;
    }

    const updated = await form.submit(() =>
        customersApi.update(props.customer!.id, {
            name: fields.name,
            email: fields.email.trim() === '' ? null : fields.email,
            locale: fields.locale,
        }),
    );

    if (updated) {
        emit('saved', updated);
    }
}
</script>

<template>
    <AppDialog
        :open="open"
        :title="t('admin.customers.editTitle')"
        :busy="form.processing.value"
        @close="emit('close')"
    >
        <form id="edit-customer" class="flex flex-col gap-4.5" novalidate @submit.prevent="save">
            <FormAlert v-if="form.generalError.value" :message="form.generalError.value" />
            <TextField
                v-model="fields.name"
                :label="t('admin.customers.name')"
                autocomplete="off"
                :error="form.errors.name"
            />
            <TextField
                v-model="fields.email"
                :label="t('admin.customers.email')"
                type="email"
                inputmode="email"
                dir="ltr"
                optional
                autocomplete="off"
                :error="form.errors.email"
            />
            <div class="flex flex-col gap-2">
                <span class="text-sm font-semibold">{{ t('admin.customers.languageLabel') }}</span>
                <SegmentedControl
                    v-model="fields.locale"
                    :label="t('admin.customers.languageLabel')"
                    :options="languages"
                />
            </div>
            <div class="flex flex-col gap-2">
                <span class="text-sm font-semibold">{{ t('admin.customers.phone') }}</span>
                <div
                    class="flex h-13 items-center gap-2.5 rounded-xl border border-line-strong bg-surface-muted px-3.5 text-fg-muted"
                >
                    <AppIcon name="lock" :size="18" />
                    <span dir="ltr">{{ customer?.phone_number ? localizeDigits(customer.phone_number) : '—' }}</span>
                </div>
                <p class="text-[13px] leading-relaxed text-fg-muted">{{ t('admin.customers.phoneLocked') }}</p>
            </div>
        </form>
        <template #footer>
            <AppButton type="submit" form="edit-customer" size="md" :loading="form.processing.value">
                {{ t('admin.customers.save') }}
            </AppButton>
            <AppButton variant="secondary" size="md" :disabled="form.processing.value" @click="emit('close')">
                {{ t('common.cancel') }}
            </AppButton>
        </template>
    </AppDialog>
</template>
