<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApiForm } from '@/shared/composables/useApiForm';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import { categoriesApi } from '../api';
import { branchIds, childrenOf } from '../tree';
import type { CategoryNode } from '../types';
import CategoryParentSelect from './CategoryParentSelect.vue';

/** "Move to…": the keyboard- and phone-friendly alternative to dragging a category elsewhere. */
const props = defineProps<{ node: CategoryNode | null; tree: CategoryNode[] }>();
const emit = defineEmits<{ close: []; moved: [tree: CategoryNode[], node: CategoryNode] }>();

const { t } = useI18n();
const form = useApiForm(['parent_id', 'ids'] as const);
const parentId = ref<number | null>(null);

const excluded = computed(() => (props.node ? branchIds(props.node) : new Set<number>()));

watch(
    () => props.node,
    (node) => {
        if (node) {
            form.clearErrors();
            parentId.value = node.parent_id;
        }
    },
);

async function move(): Promise<void> {
    const node = props.node;

    if (!node) {
        return;
    }

    if (parentId.value === node.parent_id) {
        emit('close');

        return;
    }

    // The category goes last among its new siblings.
    const ids = [...childrenOf(props.tree, parentId.value).map((child) => child.id), node.id];
    const tree = await form.submit(() => categoriesApi.reorder(parentId.value, ids));

    if (tree) {
        emit('moved', tree, node);
    }
}
</script>

<template>
    <AppDialog
        :open="node !== null"
        :title="t('admin.categories.moveTitle', { name: node?.name ?? '' })"
        icon="folderMove"
        :busy="form.processing.value"
        @close="emit('close')"
    >
        <form id="move-category" class="flex flex-col gap-4.5" novalidate @submit.prevent="move">
            <FormAlert v-if="form.generalError.value" :message="form.generalError.value" />
            <CategoryParentSelect
                v-model="parentId"
                :label="t('admin.categories.moveTarget')"
                :tree="tree"
                :exclude="excluded"
                :error="form.errors.parent_id ?? form.errors.ids"
            />
            <p class="text-[13px] leading-relaxed text-fg-muted">{{ t('admin.categories.moveHint') }}</p>
        </form>
        <template #footer>
            <AppButton type="submit" form="move-category" size="md" :loading="form.processing.value">
                {{ t('admin.categories.moveConfirm') }}
            </AppButton>
            <AppButton variant="secondary" size="md" :disabled="form.processing.value" @click="emit('close')">
                {{ t('common.cancel') }}
            </AppButton>
        </template>
    </AppDialog>
</template>
