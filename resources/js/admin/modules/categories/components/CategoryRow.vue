<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import ActionMenu, { type ActionMenuItem } from '@/shared/ui/ActionMenu.vue';
import AppBadge from '@/shared/ui/AppBadge.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import { useCategoryTree, type CategoryAction } from '../context';
import { ratingOf } from '../../seo/analysis';
import SeoScoreDot from '../../seo/components/SeoScoreDot.vue';
import type { CategoryNode } from '../types';

const props = withDefaults(
    defineProps<{
        node: CategoryNode;
        /** Position among its siblings; omitted in search results, where reordering is not offered. */
        index?: number;
        siblingCount?: number;
        /** Ancestor names, shown in search results. */
        path?: string[];
        draggable?: boolean;
    }>(),
    { draggable: false },
);

const { t } = useI18n();
const { formatNumber } = useLocale();

const seoLabel = computed(() => {
    const rating = ratingOf(props.node.seo_score);

    return rating === null
        ? t('admin.seo.notAnalysed')
        : t('admin.seo.scoreLabel', {
              score: formatNumber(props.node.seo_score ?? 0),
              rating: t(`admin.seo.rating.${rating}`),
          });
});
const tree = useCategoryTree();

const hasChildren = computed(() => props.node.children.length > 0);
const collapsed = computed(() => tree.isCollapsed(props.node.id));

const menuItems = computed<ActionMenuItem[]>(() => {
    const items: ActionMenuItem[] = [
        { key: 'edit', label: t('admin.categories.edit'), icon: 'pen' },
        { key: 'addChild', label: t('admin.categories.addChild'), icon: 'subItem' },
    ];

    if (props.index !== undefined && props.siblingCount !== undefined) {
        if (props.index > 0) {
            items.push({ key: 'moveUp', label: t('admin.categories.moveUp'), icon: 'arrowUp', separated: true });
        }

        if (props.index < props.siblingCount - 1) {
            items.push({
                key: 'moveDown',
                label: t('admin.categories.moveDown'),
                icon: 'arrowDown',
                separated: props.index === 0,
            });
        }
    }

    items.push(
        { key: 'moveTo', label: t('admin.categories.moveTo'), icon: 'folderMove' },
        props.node.is_active
            ? { key: 'toggleVisibility', label: t('admin.categories.hide'), icon: 'eyeOff', separated: true }
            : { key: 'toggleVisibility', label: t('admin.categories.show'), icon: 'eye', separated: true },
        { key: 'delete', label: t('admin.categories.delete'), icon: 'trash', danger: true },
    );

    return items;
});
</script>

<template>
    <div class="flex min-h-16 items-center gap-2 rounded-xl px-2 py-2 hover:bg-surface-muted/60 sm:gap-3 sm:px-3">
        <span
            v-if="draggable"
            class="drag-handle hidden size-8 shrink-0 cursor-grab items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-fg active:cursor-grabbing lg:flex"
            :title="t('admin.categories.dragHint')"
            aria-hidden="true"
        >
            <AppIcon name="grip" :size="18" />
        </span>

        <button
            v-if="hasChildren && path === undefined"
            type="button"
            :aria-expanded="!collapsed"
            :aria-label="t(collapsed ? 'admin.categories.expand' : 'admin.categories.collapse', { name: node.name })"
            class="flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            @click="tree.toggleCollapsed(node.id)"
        >
            <AppIcon
                name="chevronDown"
                :size="18"
                :class="['transition-transform duration-150', collapsed && '-rotate-90 rtl:rotate-90']"
            />
        </button>
        <span v-else-if="path === undefined" class="size-8 shrink-0" aria-hidden="true" />

        <span
            class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-surface-muted text-fg-muted"
        >
            <img
                v-if="node.image"
                :src="node.image.thumb ?? node.image.original"
                alt=""
                class="size-full object-cover"
                loading="lazy"
                width="44"
                height="44"
            />
            <AppIcon v-else name="tag" :size="18" />
        </span>

        <span class="flex min-w-0 grow flex-col gap-0.5">
            <span v-if="path && path.length > 0" class="truncate text-xs text-fg-muted">{{ path.join(' / ') }}</span>
            <span class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                <SeoScoreDot :rating="ratingOf(node.seo_score)" :label="seoLabel" size="sm" />
                <button
                    type="button"
                    :class="[
                        'max-w-full cursor-pointer truncate rounded text-start font-semibold hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                        node.is_visible ? 'text-fg' : 'text-fg-muted',
                    ]"
                    @click="tree.act('edit', node)"
                >
                    {{ node.name }}
                </button>
                <AppBadge v-if="!node.is_active" tone="warning">{{ t('admin.categories.hidden') }}</AppBadge>
                <AppBadge v-else-if="!node.is_visible" tone="outline">{{
                    t('admin.categories.hiddenByParent')
                }}</AppBadge>
            </span>
            <span class="truncate text-xs text-fg-muted" dir="ltr">/{{ node.slug }}</span>
        </span>

        <span v-if="hasChildren" class="hidden text-[13px] whitespace-nowrap text-fg-muted sm:inline">
            {{ t('admin.categories.childCount', { n: formatNumber(node.children.length) }) }}
        </span>

        <ActionMenu
            v-if="tree.canManage.value"
            :label="t('admin.categories.actionsFor', { name: node.name })"
            :items="menuItems"
            @select="tree.act($event as CategoryAction, node)"
        />
    </div>
</template>
