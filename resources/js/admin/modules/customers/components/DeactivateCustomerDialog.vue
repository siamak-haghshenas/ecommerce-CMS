<script setup lang="ts">
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApiForm } from '@/shared/composables/useApiForm';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import { customersApi } from '../api';
import type { Customer } from '../types';

const props = defineProps<{ open: boolean; customer: Customer | null }>();
const emit = defineEmits<{ close: []; done: [customer: Customer] }>();

const { t } = useI18n();
const form = useApiForm([] as const);

watch(
    () => props.open,
    (open) => open && form.clearErrors(),
);

async function confirm(): Promise<void> {
    const updated = props.customer
        ? await form.submit(() => customersApi.setActive(props.customer!.id, false))
        : undefined;

    if (updated) {
        emit('done', updated);
    }
}
</script>

<template>
    <AppDialog
        :open="open"
        :title="t('admin.customers.deactivateTitle', { name: customer?.name ?? '' })"
        icon="ban"
        tone="danger"
        :busy="form.processing.value"
        @close="emit('close')"
    >
        <FormAlert v-if="form.generalError.value" :message="form.generalError.value" />
        <ul class="flex flex-col gap-1.5">
            <li
                v-for="point in ['deactivatePoint1', 'deactivatePoint2', 'deactivatePoint3']"
                :key="point"
                class="flex gap-2.5 text-sm leading-relaxed"
            >
                <AppIcon name="check" :size="16" class="mt-1 text-fg-muted" />
                {{ t(`admin.customers.${point}`) }}
            </li>
        </ul>
        <template #footer>
            <AppButton variant="danger" size="md" :loading="form.processing.value" @click="confirm">
                <AppIcon name="ban" :size="18" />
                {{ t('admin.customers.deactivateConfirm') }}
            </AppButton>
            <AppButton variant="secondary" size="md" :disabled="form.processing.value" @click="emit('close')">
                {{ t('common.cancel') }}
            </AppButton>
        </template>
    </AppDialog>
</template>
