<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { paths } from '../links';
import { formatNumber } from '../money';
import { useCartStore } from '../stores/cart';
import { useSessionStore } from '../stores/session';
import StoreIcon, { type StoreIconName } from './StoreIcon.vue';

const route = useRoute();
const cart = useCartStore();
const session = useSessionStore();

const tabs = computed<{ label: string; to: string; icon: StoreIconName; badge?: boolean }[]>(() => [
    { label: 'خانه', to: paths.home, icon: 'home' },
    { label: 'دسته‌بندی', to: paths.categories, icon: 'grid' },
    { label: 'سبد خرید', to: paths.cart, icon: 'bag', badge: true },
    { label: 'حساب من', to: session.isLoggedIn ? paths.account : paths.login, icon: 'user' },
]);
</script>

<template>
    <nav
        aria-label="ناوبری اصلی"
        class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-4 border-t border-line bg-surface pt-2 pb-[max(env(safe-area-inset-bottom),12px)] text-xs lg:hidden"
    >
        <RouterLink
            v-for="tab in tabs"
            :key="tab.label"
            :to="tab.to"
            :aria-current="route.path === tab.to ? 'page' : undefined"
            class="relative flex min-h-11 flex-col items-center gap-0.5"
            :class="route.path === tab.to ? 'font-semibold text-primary' : 'text-muted'"
        >
            <StoreIcon :name="tab.icon" class="size-6" />
            {{ tab.label }}
            <span
                v-if="tab.badge && cart.itemsCount > 0"
                class="absolute -top-0.5 left-[calc(50%-22px)] grid h-4 min-w-4 place-items-center rounded-[5px] bg-primary px-1 text-[10px] font-bold text-white"
            >
                <span class="sr-only">، </span>{{ formatNumber(cart.itemsCount) }}<span class="sr-only"> کالا</span>
            </span>
        </RouterLink>
    </nav>
</template>
