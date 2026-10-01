<script setup lang="ts">
import { reactive, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApiForm } from '@/shared/composables/useApiForm';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import PhoneField from '@/shared/ui/PhoneField.vue';
import TextField from '@/shared/ui/TextField.vue';
import type { StaffRole } from '../../../permissions';
import { staffApi } from '../api';
import type { StaffMember } from '../types';
import RolePicker from './RolePicker.vue';

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: []; added: [member: StaffMember] }>();

const { t } = useI18n();
const form = useApiForm(['phone_number', 'name', 'role'] as const);
const fields = reactive({ phone: '', name: '', role: 'admin' as StaffRole });

watch(
    () => props.open,
    (open) => {
        if (open) {
            form.clearErrors();
            Object.assign(fields, { phone: '', name: '', role: 'admin' });
        }
    },
);

async function add(): Promise<void> {
    const member = await form.submit(() =>
        staffApi.add({ phone_number: fields.phone, name: fields.name.trim() || undefined, role: fields.role }),
    );

    if (member) {
        emit('added', member);
    }
}
</script>

<template>
    <AppDialog
        :open="open"
        :title="t('admin.staff.addTitle')"
        icon="userPlus"
        :busy="form.processing.value"
        @close="emit('close')"
    >
        <form id="add-staff" class="flex flex-col gap-4.5" novalidate @submit.prevent="add">
            <FormAlert v-if="form.generalError.value" :message="form.generalError.value" />
            <PhoneField v-model="fields.phone" :error="form.errors.phone_number" autocomplete="off" />
            <RolePicker v-model="fields.role" :error="form.errors.role" />
            <TextField
                v-model="fields.name"
                :label="`${t('admin.staff.name')} ${t('admin.staff.nameOptional')}`"
                :placeholder="t('admin.staff.namePlaceholder')"
                :hint="t('admin.staff.nameHint')"
                autocomplete="off"
                :error="form.errors.name"
            />
            <p class="flex gap-2.5 rounded-xl bg-surface-muted px-3.5 py-3 text-[13px] leading-relaxed">
                <AppIcon name="message" :size="18" class="text-fg-muted" />
                {{ t('admin.staff.smsNote') }}
            </p>
        </form>
        <template #footer>
            <AppButton type="submit" form="add-staff" size="md" :loading="form.processing.value">
                <AppIcon name="userPlus" :size="18" />
                {{ t('admin.staff.add') }}
            </AppButton>
            <AppButton variant="secondary" size="md" :disabled="form.processing.value" @click="emit('close')">
                {{ t('common.cancel') }}
            </AppButton>
        </template>
    </AppDialog>
</template>
