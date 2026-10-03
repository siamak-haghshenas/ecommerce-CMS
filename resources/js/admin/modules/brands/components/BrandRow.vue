<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import ActionMenu, { type ActionMenuItem } from '@/shared/ui/ActionMenu.vue';
import AppBadge from '@/shared/ui/AppBadge.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import { ratingOf } from '../../seo/analysis';
import SeoScoreDot from '../../seo/components/SeoScoreDot.vue';
import type { Brand } from '../types';

export type BrandAction = 'edit' | 'moveUp' | 'moveDown' | 'toggleVisibility' | 'delete';

const props = defineProps<{
    brand: Brand;
    canManage: boolean;
    /** Shows the drag handle and the move up/down actions (not while filtering). */
    reorderable: boolean;
    isFirst: boolean;
    isLast: boolean;
}>();
const emit = defineEmits<{ act: [action: BrandAction, brand: Brand] }>();

const { t } = useI18n();
const { formatNumber } = useLocale();

const seoLabel = computed(() => {
    const rating = ratingOf(props.brand.seo_score);

    return rating === null
        ? t('admin.seo.notAnalysed')
        : t('admin.seo.scoreLabel', {
              score: formatNumber(props.brand.seo_score ?? 0),
              rating: t(`admin.seo.rating.${rating}`),
          });
});

const menuItems = computed<ActionMenuItem[]>(() => {
    const items: ActionMenuItem[] = [{ key: 'edit', label: t('admin.brands.edit'), icon: 'pen' }];

    if (props.reorderable && !props.isFirst) {
        items.push({ key: 'moveUp', label: t('admin.brands.moveUp'), icon: 'arrowUp', separated: true });
    }

    if (props.reorderable && !props.isLast) {
        items.push({ key: 'moveDown', label: t('admin.brands.moveDown'), icon: 'arrowDown', separated: props.isFirst });
    }

    items.push(
        props.brand.is_active
            ? { key: 'toggleVisibility', label: t('admin.brands.hide'), icon: 'eyeOff', separated: true }
            : { key: 'toggleVisibility', label: t('admin.brands.show'), icon: 'eye', separated: true },
        { key: 'delete', label: t('admin.brands.delete'), icon: 'trash', danger: true },
    );

    return items;
});
</script>

<template>
    <div class="flex min-h-16 items-center gap-2 rounded-xl px-2 py-2 hover:bg-surface-muted/60 sm:gap-3 sm:px-3">
        <span
            v-if="reorderable && canManage"
            class="drag-handle hidden size-8 shrink-0 cursor-grab items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-fg active:cursor-grabbing lg:flex"
            :title="t('admin.brands.dragHint')"
            aria-hidden="true"
        >
            <AppIcon name="grip" :size="18" />
        </span>

        <span
            class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-surface text-fg-muted"
        >
            <img
                v-if="brand.logo"
                :src="brand.logo.thumb"
                alt=""
                class="size-full object-contain p-1"
                loading="lazy"
                width="48"
                height="48"
            />
            <AppIcon v-else name="award" :size="20" />
        </span>

        <span class="flex min-w-0 grow flex-col gap-0.5">
            <span class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                <SeoScoreDot :rating="ratingOf(brand.seo_score)" :label="seoLabel" size="sm" />
                <button
                    type="button"
                    :class="[
                        'max-w-full cursor-pointer truncate rounded text-start font-semibold hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                        brand.is_active ? 'text-fg' : 'text-fg-muted',
                    ]"
                    @click="emit('act', 'edit', brand)"
                >
                    {{ brand.name }}
                </button>
                <span v-if="brand.persian_name" dir="rtl" class="truncate text-sm text-fg-muted">{{
                    brand.persian_name
                }}</span>
                <AppBadge v-if="!brand.is_active" tone="warning">{{ t('admin.brands.hidden') }}</AppBadge>
            </span>
            <span class="truncate text-xs text-fg-muted" dir="ltr">/{{ brand.slug }}</span>
        </span>

        <a
            v-if="brand.website"
            :href="brand.website"
            target="_blank"
            rel="noopener noreferrer"
            :aria-label="t('admin.brands.visitWebsite', { name: brand.name })"
            :title="brand.website"
            class="hidden size-10 shrink-0 items-center justify-center rounded-[10px] text-fg-muted hover:bg-surface-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none sm:flex"
        >
            <AppIcon name="externalLink" :size="18" />
        </a>

        <ActionMenu
            v-if="canManage"
            :label="t('admin.brands.actionsFor', { name: brand.name })"
            :items="menuItems"
            @select="emit('act', $event as BrandAction, brand)"
        />
    </div>
</template>
