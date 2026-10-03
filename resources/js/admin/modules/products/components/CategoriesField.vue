<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import { flatten } from '../../categories/tree';
import type { CategoryNode } from '../../categories/types';

/**
 * A product's categories (several) with one primary category, used for breadcrumbs and URLs.
 * The first added category becomes primary until another one is chosen.
 */
const props = defineProps<{ tree: CategoryNode[]; error?: string }>();
const ids = defineModel<number[]>('ids', { required: true });
const primaryId = defineModel<number | null>('primaryId', { required: true });

const { t } = useI18n();
const id = useId();
const toAdd = ref('');

const all = computed(() => flatten(props.tree));
const byId = computed(() => new Map(all.value.map((item) => [item.node.id, item])));
const chosen = computed(() => ids.value.map((categoryId) => byId.value.get(categoryId)).filter((item) => !!item));
const available = computed(() => all.value.filter((item) => !ids.value.includes(item.node.id)));

function add(): void {
    const categoryId = Number(toAdd.value);

    if (categoryId > 0) {
        ids.value = [...ids.value, categoryId];
        primaryId.value ??= categoryId;
    }
    toAdd.value = '';
}

function remove(categoryId: number): void {
    // Use the new list: a defineModel value only updates after the parent re-renders.
    const next = ids.value.filter((item) => item !== categoryId);
    ids.value = next;

    if (primaryId.value === categoryId) {
        primaryId.value = next[0] ?? null;
    }
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <span :id="`${id}-label`" class="text-sm font-semibold text-fg">{{ t('admin.products.categories') }}</span>
        <p class="text-[13px] leading-relaxed text-fg-muted">{{ t('admin.products.categoriesHint') }}</p>

        <ul
            v-if="chosen.length > 0"
            class="flex flex-col divide-y divide-line rounded-xl border border-line"
            :aria-labelledby="`${id}-label`"
        >
            <li v-for="item in chosen" :key="item!.node.id" class="flex items-center gap-2 px-3 py-2">
                <span class="flex min-w-0 grow flex-col">
                    <span v-if="item!.path.length > 0" class="truncate text-xs text-fg-muted">{{
                        item!.path.join(' / ')
                    }}</span>
                    <span class="truncate text-sm font-medium">{{ item!.node.name }}</span>
                </span>
                <label class="flex shrink-0 cursor-pointer items-center gap-1.5 text-xs text-fg-muted">
                    <input
                        type="radio"
                        :name="`${id}-primary`"
                        :checked="primaryId === item!.node.id"
                        class="size-4 accent-[var(--color-primary)]"
                        @change="primaryId = item!.node.id"
                    />
                    {{ t('admin.products.primaryCategory') }}
                </label>
                <button
                    type="button"
                    :aria-label="t('admin.products.removeCategory', { name: item!.node.name })"
                    class="flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-fg-muted hover:bg-danger-soft hover:text-danger focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    @click="remove(item!.node.id)"
                >
                    <AppIcon name="close" :size="16" />
                </button>
            </li>
        </ul>

        <div class="flex flex-wrap items-center gap-2">
            <label :for="`${id}-add`" class="sr-only">{{ t('admin.products.addCategory') }}</label>
            <select
                :id="`${id}-add`"
                v-model="toAdd"
                class="h-11 min-w-0 grow cursor-pointer rounded-[10px] border border-line-strong bg-surface px-3 text-sm text-fg outline-none focus:border-primary focus:ring-4 focus:ring-primary-soft"
            >
                <option value="">{{ t('admin.products.chooseCategory') }}</option>
                <option v-for="item in available" :key="item.node.id" :value="String(item.node.id)">
                    {{ '— '.repeat(item.depth) }}{{ item.node.name }}
                </option>
            </select>
            <AppButton variant="secondary" size="md" :disabled="toAdd === ''" @click="add">
                <AppIcon name="plus" :size="18" />
                {{ t('admin.products.addCategory') }}
            </AppButton>
        </div>
        <p v-if="error" role="alert" class="text-[13px] text-danger">{{ error }}</p>
    </div>
</template>
