<script setup lang="ts">
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApiForm } from '@/shared/composables/useApiForm';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';

/** Confirms deleting an attribute or option; the parent passes the delete request. */
const props = defineProps<{
    item: { id: number; name: string } | null;
    kind: 'attribute' | 'option';
    remove: (id: number) => Promise<void>;
}>();
const emit = defineEmits<{ close: []; deleted: [name: string] }>();

const { t } = useI18n();
const form = useApiForm([] as const);

watch(
    () => props.item,
    (item) => item && form.clearErrors(),
);

async function confirm(): Promise<void> {
    const item = props.item;

    if (!item) {
        return;
    }

    const done = await form.submit(async () => (await props.remove(item.id), true));

    if (done) {
        emit('deleted', item.name);
    }
}
</script>

<template>
    <AppDialog
        :open="item !== null"
        :title="t('admin.attributes.deleteTitle', { name: item?.name ?? '' })"
        icon="trash"
        tone="danger"
        :busy="form.processing.value"
        @close="emit('close')"
    >
        <FormAlert v-if="form.generalError.value" :message="form.generalError.value" />
        <p class="text-sm leading-loose">
            {{
                kind === 'attribute'
                    ? t('admin.attributes.deleteAttributeText')
                    : t('admin.attributes.deleteOptionText')
            }}
        </p>
        <template #footer>
            <AppButton variant="danger" size="md" :loading="form.processing.value" @click="confirm">
                {{ t('admin.attributes.deleteConfirm') }}
            </AppButton>
            <AppButton variant="secondary" size="md" :disabled="form.processing.value" @click="emit('close')">
                {{ t('common.cancel') }}
            </AppButton>
        </template>
    </AppDialog>
</template>
