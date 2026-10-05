<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ActionMenu, { type ActionMenuItem } from '@/shared/ui/ActionMenu.vue';
import AppBadge from '@/shared/ui/AppBadge.vue';
import AppIcon, { type IconName } from '@/shared/ui/AppIcon.vue';
import { displayLabel, type MenuRow } from '../tree';

/** One item of the menu tree: what it links to, whether storefronts show it, and its actions. */
const props = defineProps<{
    row: MenuRow;
    sub: boolean;
    canManage: boolean;
    isFirst: boolean;
    isLast: boolean;
}>();
const emit = defineEmits<{ act: [action: string] }>();

const { t } = useI18n();

const TYPE_ICONS: Record<MenuRow['type'], IconName> = { page: 'file', category: 'tag', url: 'globe' };

const actions = computed<ActionMenuItem[]>(() => {
    const items: ActionMenuItem[] = [{ key: 'edit', label: t('admin.menus.edit'), icon: 'pen' }];

    if (!props.sub) {
        items.push({ key: 'addSub', label: t('admin.menus.addSubItem'), icon: 'plus' });
    }

    if (!props.isFirst) {
        items.push({ key: 'up', label: t('admin.menus.moveUp'), icon: 'arrowUp', separated: true });
    }

    if (!props.isLast) {
        items.push({ key: 'down', label: t('admin.menus.moveDown'), icon: 'arrowDown', separated: props.isFirst });
    }

    if (props.sub) {
        items.push({ key: 'outdent', label: t('admin.menus.outdent'), icon: 'arrowForward' });
    } else if (!props.isFirst && props.row.children.length === 0) {
        items.push({ key: 'indent', label: t('admin.menus.indent'), icon: 'subItem' });
    }

    items.push({ key: 'remove', label: t('admin.menus.remove'), icon: 'trash', danger: true, separated: true });

    return items;
});

const detail = computed(() =>
    props.row.type === 'url'
        ? props.row.targetDetail
        : `${t(props.row.categoryType === 'blog' ? 'admin.menus.categoryGroups.blog' : `admin.menus.types.${props.row.type}`)} · /${props.row.targetDetail}`,
);
</script>

<template>
    <div
        :class="[
            'flex min-h-14 items-center gap-2 px-3 py-2 sm:gap-3 sm:px-4',
            sub && 'ps-8 sm:ps-12',
            row.hiddenReason && 'bg-surface-muted/40',
        ]"
    >
        <span
            v-if="canManage"
            class="drag-handle hidden size-8 shrink-0 cursor-grab items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-fg active:cursor-grabbing lg:flex"
            :aria-hidden="true"
        >
            <AppIcon name="grip" :size="18" />
        </span>
        <AppIcon v-if="sub" name="subItem" :size="16" class="shrink-0 text-fg-muted" flip-in-rtl />
        <span
            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-surface-muted text-fg-muted"
            :title="t(`admin.menus.types.${row.type}`)"
        >
            <AppIcon :name="TYPE_ICONS[row.type]" :size="18" />
        </span>
        <span class="flex min-w-0 grow flex-col">
            <span class="flex flex-wrap items-center gap-2">
                <span :class="['truncate font-semibold', row.hiddenReason && 'text-fg-muted']">{{
                    displayLabel(row)
                }}</span>
                <AppIcon
                    v-if="row.openInNewTab"
                    name="externalLink"
                    :size="14"
                    class="text-fg-muted"
                    :aria-label="t('admin.menus.newTabBadge')"
                />
                <AppBadge v-if="row.hiddenReason" tone="warning">{{
                    t(`admin.menus.hidden.${row.hiddenReason}`)
                }}</AppBadge>
            </span>
            <span class="truncate text-start text-xs text-fg-muted" :dir="row.type === 'url' ? 'ltr' : undefined">{{
                detail
            }}</span>
        </span>
        <ActionMenu
            v-if="canManage"
            :label="t('admin.menus.actionsFor', { name: displayLabel(row) })"
            :items="actions"
            @select="emit('act', $event)"
        />
    </div>
</template>
