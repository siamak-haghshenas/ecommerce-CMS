<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApiForm } from '@/shared/composables/useApiForm';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import type { StaffRole } from '../../../permissions';
import { staffApi } from '../api';
import type { StaffMember } from '../types';
import RolePicker from './RolePicker.vue';

const props = defineProps<{ open: boolean; member: StaffMember | null }>();
const emit = defineEmits<{ close: []; done: [member: StaffMember] }>();

const { t } = useI18n();
const form = useApiForm(['role'] as const);
const role = ref<StaffRole>('admin');

watch(
    () => props.open,
    (open) => {
        if (open && props.member) {
            form.clearErrors();
            role.value = props.member.role;
        }
    },
);

async function save(): Promise<void> {
    const updated = props.member
        ? await form.submit(() => staffApi.changeRole(props.member!.id, role.value))
        : undefined;

    if (updated) {
        emit('done', updated);
    }
}
</script>

<template>
    <AppDialog
        :open="open"
        :title="t('admin.staff.changeRoleTitle', { name: member?.name ?? '' })"
        icon="swap"
        :busy="form.processing.value"
        @close="emit('close')"
    >
        <FormAlert v-if="form.generalError.value" :message="form.generalError.value" />
        <RolePicker v-model="role" :error="form.errors.role" />
        <template #footer>
            <AppButton size="md" :disabled="role === member?.role" :loading="form.processing.value" @click="save">
                {{ t('admin.staff.saveRole') }}
            </AppButton>
            <AppButton variant="secondary" size="md" :disabled="form.processing.value" @click="emit('close')">
                {{ t('common.cancel') }}
            </AppButton>
        </template>
    </AppDialog>
</template>
