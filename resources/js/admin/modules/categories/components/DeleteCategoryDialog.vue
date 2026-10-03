<script setup lang="ts">
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApiForm } from '@/shared/composables/useApiForm';
import { useLocale } from '@/shared/composables/useLocale';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import { categoriesApi } from '../api';
import type { CategoryNode } from '../types';

/**
 * Deleting is refused while the category has sub-categories: nothing is moved automatically,
 * so the dialog explains what to do instead (the API enforces the same rule).
 */
const props = defineProps<{ node: CategoryNode | null }>();
const emit = defineEmits<{ close: []; deleted: [node: CategoryNode] }>();

const { t } = useI18n();
const { formatNumber } = useLocale();
const form = useApiForm([] as const);

const childCount = computed(() => props.node?.children.length ?? 0);

watch(
    () => props.node,
    (node) => node && form.clearErrors(),
);

async function confirm(): Promise<void> {
    const node = props.node;

    if (!node) {
        return;
    }

    const done = await form.submit(async () => (await categoriesApi.remove(node.id), node));

    if (done) {
        emit('deleted', done);
    }
}
</script>

<template>
    <AppDialog
        :open="node !== null"
        :title="t('admin.categories.deleteTitle', { name: node?.name ?? '' })"
        icon="trash"
        tone="danger"
        :busy="form.processing.value"
        @close="emit('close')"
    >
        <FormAlert v-if="form.generalError.value" :message="form.generalError.value" />

        <p
            v-if="childCount > 0"
            role="note"
            class="flex items-start gap-2.5 rounded-xl bg-warning-soft px-4 py-3 text-sm leading-relaxed text-fg"
        >
            <AppIcon name="info" :size="18" class="mt-0.5 shrink-0 text-warning" />
            {{ t('admin.categories.deleteBlocked', { n: formatNumber(childCount) }) }}
        </p>
        <p v-else class="text-sm leading-loose">{{ t('admin.categories.deleteText') }}</p>

        <template #footer>
            <AppButton
                variant="danger"
                size="md"
                :loading="form.processing.value"
                :disabled="childCount > 0"
                @click="confirm"
            >
                {{ t('admin.categories.deleteConfirm') }}
            </AppButton>
            <AppButton variant="secondary" size="md" :disabled="form.processing.value" @click="emit('close')">
                {{ childCount > 0 ? t('common.close') : t('common.cancel') }}
            </AppButton>
        </template>
    </AppDialog>
</template>
