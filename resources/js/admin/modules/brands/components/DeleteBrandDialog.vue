<script setup lang="ts">
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApiForm } from '@/shared/composables/useApiForm';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import { brandsApi } from '../api';
import type { Brand } from '../types';

const props = defineProps<{ brand: Brand | null }>();
const emit = defineEmits<{ close: []; deleted: [brand: Brand] }>();

const { t } = useI18n();
const form = useApiForm([] as const);

watch(
    () => props.brand,
    (brand) => brand && form.clearErrors(),
);

async function confirm(): Promise<void> {
    const brand = props.brand;

    if (!brand) {
        return;
    }

    const done = await form.submit(async () => (await brandsApi.remove(brand.id), brand));

    if (done) {
        emit('deleted', done);
    }
}
</script>

<template>
    <AppDialog
        :open="brand !== null"
        :title="t('admin.brands.deleteTitle', { name: brand?.name ?? '' })"
        icon="trash"
        tone="danger"
        :busy="form.processing.value"
        @close="emit('close')"
    >
        <FormAlert v-if="form.generalError.value" :message="form.generalError.value" />
        <p class="text-sm leading-loose">{{ t('admin.brands.deleteText') }}</p>
        <template #footer>
            <AppButton variant="danger" size="md" :loading="form.processing.value" @click="confirm">
                {{ t('admin.brands.deleteConfirm') }}
            </AppButton>
            <AppButton variant="secondary" size="md" :disabled="form.processing.value" @click="emit('close')">
                {{ t('common.cancel') }}
            </AppButton>
        </template>
    </AppDialog>
</template>
