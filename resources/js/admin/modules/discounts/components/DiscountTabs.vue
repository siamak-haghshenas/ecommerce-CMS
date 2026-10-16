<script setup lang="ts">
import { useI18n } from 'vue-i18n';

/** The tabs of Sales › Discounts: promotions, sale events and coupons. */
defineProps<{ active: 'promotions' | 'events' | 'coupons' }>();

const { t } = useI18n();

const tabClass = (on: boolean): string[] => [
    'flex h-11 items-center gap-2 border-b-2 px-1 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
    on ? 'border-primary text-fg' : 'border-transparent text-fg-muted hover:text-fg',
];
</script>

<template>
    <nav :aria-label="t('admin.discounts.title')" class="flex gap-6 overflow-x-auto border-b border-line">
        <RouterLink
            :to="{ name: 'discounts' }"
            :class="tabClass(active === 'promotions')"
            :aria-current="active === 'promotions' ? 'page' : undefined"
            >{{ t('admin.discounts.tabs.promotions') }}</RouterLink
        >
        <RouterLink
            :to="{ name: 'sale-events' }"
            :class="tabClass(active === 'events')"
            :aria-current="active === 'events' ? 'page' : undefined"
            >{{ t('admin.discounts.tabs.events') }}</RouterLink
        >
        <RouterLink
            :to="{ name: 'coupons' }"
            :class="tabClass(active === 'coupons')"
            :aria-current="active === 'coupons' ? 'page' : undefined"
            >{{ t('admin.discounts.tabs.coupons') }}</RouterLink
        >
    </nav>
</template>
