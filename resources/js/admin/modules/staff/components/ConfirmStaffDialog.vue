<script setup lang="ts">
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApiForm } from '@/shared/composables/useApiForm';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import { staffApi } from '../api';
import type { StaffMember } from '../types';

/** Confirms the two staff actions that take access away: deactivating and removing staff access. */
const props = defineProps<{ action: 'deactivate' | 'remove' | null; member: StaffMember | null }>();
const emit = defineEmits<{ close: []; done: [member: StaffMember] }>();

const { t } = useI18n();
const form = useApiForm([] as const);

const copy = computed(() =>
    props.action === 'remove'
        ? {
              title: 'admin.staff.removeTitle',
              text: 'admin.staff.removeText',
              confirm: 'admin.staff.removeConfirm',
              icon: 'userX' as const,
          }
        : {
              title: 'admin.staff.deactivateTitle',
              text: 'admin.staff.deactivateText',
              confirm: 'admin.staff.deactivateConfirm',
              icon: 'ban' as const,
          },
);

watch(
    () => props.action,
    (action) => action && form.clearErrors(),
);

async function confirm(): Promise<void> {
    const member = props.member;

    if (!member) {
        return;
    }

    const done = await form.submit(async () =>
        props.action === 'remove' ? (await staffApi.remove(member.id), member) : staffApi.setActive(member.id, false),
    );

    if (done) {
        emit('done', done);
    }
}
</script>

<template>
    <AppDialog
        :open="action !== null"
        :title="t(copy.title, { name: member?.name ?? '' })"
        :icon="copy.icon"
        tone="danger"
        :busy="form.processing.value"
        @close="emit('close')"
    >
        <FormAlert v-if="form.generalError.value" :message="form.generalError.value" />
        <p class="text-sm leading-loose">{{ t(copy.text) }}</p>
        <template #footer>
            <AppButton variant="danger" size="md" :loading="form.processing.value" @click="confirm">
                {{ t(copy.confirm) }}
            </AppButton>
            <AppButton variant="secondary" size="md" :disabled="form.processing.value" @click="emit('close')">
                {{ t('common.cancel') }}
            </AppButton>
        </template>
    </AppDialog>
</template>
