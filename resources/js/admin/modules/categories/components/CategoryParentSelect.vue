<script setup lang="ts">
import { computed, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import AppIcon from '@/shared/ui/AppIcon.vue';
import { flatten } from '../tree';
import type { CategoryNode } from '../types';

/**
 * A native select of every category, indented by depth. Categories that can't be the parent
 * (the category itself and its descendants) are listed but disabled, to keep the tree readable.
 */
const props = defineProps<{ label: string; tree: CategoryNode[]; exclude?: Set<number>; error?: string }>();
const parentId = defineModel<number | null>({ required: true });

const { t } = useI18n();
const id = useId();

const options = computed(() =>
    flatten(props.tree).map(({ node, depth }) => ({
        value: String(node.id),
        label: `${'— '.repeat(depth)}${node.name}`,
        disabled: props.exclude?.has(node.id) ?? false,
    })),
);

/** Native selects hold strings; '' is the top level. */
const selected = computed({
    get: () => (parentId.value === null ? '' : String(parentId.value)),
    set: (value: string) => {
        parentId.value = value === '' ? null : Number(value);
    },
});
</script>

<template>
    <div>
        <label :for="id" class="mb-2 block text-sm font-semibold text-fg">{{ label }}</label>
        <div
            :class="[
                'relative flex h-13 items-center rounded-xl border bg-surface',
                error
                    ? 'border-2 border-danger'
                    : 'border-line-strong focus-within:border-2 focus-within:border-primary focus-within:ring-4 focus-within:ring-primary-soft',
            ]"
        >
            <select
                :id="id"
                v-model="selected"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="error ? `${id}-error` : undefined"
                class="h-full w-full cursor-pointer appearance-none bg-transparent ps-3.5 pe-10 text-[15px] text-fg outline-none"
            >
                <option value="">{{ t('admin.categories.topLevel') }}</option>
                <option v-for="option in options" :key="option.value" :value="option.value" :disabled="option.disabled">
                    {{ option.label }}
                </option>
            </select>
            <AppIcon name="chevronDown" :size="18" class="pointer-events-none absolute end-3.5 text-icon" />
        </div>
        <p
            v-if="error"
            :id="`${id}-error`"
            class="mt-2 flex items-start gap-1.5 text-[13px] leading-relaxed text-danger"
        >
            <AppIcon name="alert" :size="16" class="mt-0.5" />
            <span>{{ error }}</span>
        </p>
    </div>
</template>
