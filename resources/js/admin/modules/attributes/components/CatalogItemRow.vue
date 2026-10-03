<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import ActionMenu, { type ActionMenuItem } from '@/shared/ui/ActionMenu.vue';
import AppBadge from '@/shared/ui/AppBadge.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';

export type CatalogItemAction = 'edit' | 'moveUp' | 'moveDown' | 'delete';

/** One attribute or option in its list: name, badges and a preview of its values. */
const props = defineProps<{
    name: string;
    slug: string;
    badges: string[];
    values: { value: string; color?: string | null }[];
    meta?: string;
    canManage: boolean;
    isFirst: boolean;
    isLast: boolean;
}>();
const emit = defineEmits<{ act: [action: CatalogItemAction] }>();

const { t } = useI18n();
const { formatNumber } = useLocale();

const PREVIEW = 6;
const preview = computed(() => props.values.slice(0, PREVIEW));
const more = computed(() => props.values.length - preview.value.length);

const menuItems = computed<ActionMenuItem[]>(() => [
    { key: 'edit', label: t('admin.attributes.edit'), icon: 'pen' },
    ...(props.isFirst
        ? []
        : [{ key: 'moveUp', label: t('admin.attributes.moveUp'), icon: 'arrowUp' as const, separated: true }]),
    ...(props.isLast
        ? []
        : [
              {
                  key: 'moveDown',
                  label: t('admin.attributes.moveDown'),
                  icon: 'arrowDown' as const,
                  separated: props.isFirst,
              },
          ]),
    { key: 'delete', label: t('admin.attributes.delete'), icon: 'trash', danger: true, separated: true },
]);
</script>

<template>
    <div class="flex min-h-16 items-center gap-2 rounded-xl px-2 py-2.5 hover:bg-surface-muted/60 sm:gap-3 sm:px-3">
        <span
            v-if="canManage"
            class="drag-handle hidden size-8 shrink-0 cursor-grab items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-fg active:cursor-grabbing lg:flex"
            :title="t('admin.attributes.dragHint')"
            aria-hidden="true"
        >
            <AppIcon name="grip" :size="18" />
        </span>

        <span class="flex min-w-0 grow flex-col gap-1.5">
            <span class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                <button
                    type="button"
                    class="max-w-full cursor-pointer truncate rounded text-start font-semibold text-fg hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                    @click="emit('act', 'edit')"
                >
                    {{ name }}
                </button>
                <span class="text-xs text-fg-muted" dir="ltr">{{ slug }}</span>
                <AppBadge v-for="badge in badges" :key="badge">{{ badge }}</AppBadge>
            </span>
            <span v-if="values.length > 0" class="flex flex-wrap items-center gap-1.5">
                <span
                    v-for="item in preview"
                    :key="item.value"
                    class="inline-flex h-6 items-center gap-1.5 rounded-full border border-line px-2.5 text-xs text-fg"
                >
                    <span
                        v-if="item.color"
                        class="size-3 rounded-full border border-line-strong"
                        :style="{ backgroundColor: item.color }"
                        aria-hidden="true"
                    />
                    {{ item.value }}
                </span>
                <span v-if="more > 0" class="text-xs text-fg-muted">
                    {{ t('admin.attributes.moreValues', { n: formatNumber(more) }) }}
                </span>
            </span>
        </span>

        <span v-if="meta" class="hidden text-[13px] whitespace-nowrap text-fg-muted sm:inline">{{ meta }}</span>

        <ActionMenu
            v-if="canManage"
            :label="t('admin.attributes.actionsFor', { name })"
            :items="menuItems"
            @select="emit('act', $event as CatalogItemAction)"
        />
    </div>
</template>
