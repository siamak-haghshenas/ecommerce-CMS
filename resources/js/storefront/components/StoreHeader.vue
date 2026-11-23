<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { contentApi } from '../api';
import { store } from '../content';
import { menuLink, paths, type MenuLink } from '../links';
import { formatNumber } from '../money';
import { useCartStore } from '../stores/cart';
import { useSessionStore } from '../stores/session';
import MenuLinkItem from './MenuLinkItem.vue';
import StoreIcon from './StoreIcon.vue';
import StoreLogo from './StoreLogo.vue';

const route = useRoute();
const router = useRouter();
const cart = useCartStore();
const session = useSessionStore();

const query = ref('');
const menu = ref<MenuLink[]>([]);

watch(
    () => route.query.q,
    (q) => {
        query.value = typeof q === 'string' ? q : '';
    },
    { immediate: true },
);

const accountLabel = computed(() =>
    session.customer === null ? 'ورود | ثبت‌نام' : `${session.customer.name ?? 'حساب من'}، حساب من`,
);
const cartLabel = computed(() => `سبد خرید، ${formatNumber(cart.itemsCount)} کالا`);

function search(): void {
    const q = query.value.trim();

    if (q !== '') {
        void router.push(paths.search(q));
    }
}

onMounted(async () => {
    try {
        menu.value = (await contentApi.menu('header')).map(menuLink);
    } catch {
        // Without the menu the bar keeps its fixed links.
    }
});
</script>

<template>
    <header class="bg-ink text-white">
        <div
            class="max-w-store mx-auto flex flex-wrap items-center gap-x-7 gap-y-3 px-4 pt-3 pb-3.5 lg:flex-nowrap lg:px-6 lg:py-3.5"
        >
            <StoreLogo class="max-lg:hidden" />
            <StoreLogo size="sm" class="lg:hidden" />

            <RouterLink :to="paths.categories" class="ms-auto text-xs text-line-strong hover:text-white lg:hidden">
                دسته‌بندی کالاها
            </RouterLink>

            <form role="search" class="order-last w-full lg:order-none lg:max-w-160 lg:flex-1" @submit.prevent="search">
                <label
                    class="text-ink flex h-11 items-center gap-2.5 rounded-[10px] bg-surface px-3 lg:h-[46px] lg:px-4"
                >
                    <StoreIcon name="search" :stroke-width="2" class="text-muted size-5" />
                    <span class="sr-only">جستجو</span>
                    <input
                        v-model="query"
                        type="search"
                        enterkeyhint="search"
                        placeholder="نام کالا، برند یا دسته را جستجو کنید"
                        class="text-ink placeholder:text-muted-2 min-w-0 flex-1 border-0 bg-transparent outline-none"
                    />
                </label>
            </form>

            <div class="ms-auto hidden items-center gap-3 lg:flex">
                <RouterLink
                    :to="session.isLoggedIn ? paths.account : paths.login"
                    class="border-line-dark flex h-11 items-center gap-2 rounded-[10px] border px-4 font-medium text-white hover:text-white"
                >
                    <StoreIcon name="user" class="size-5" />
                    {{ accountLabel }}
                </RouterLink>
                <RouterLink
                    :to="paths.cart"
                    :aria-label="cartLabel"
                    class="flex h-11 items-center gap-2 rounded-[10px] bg-primary px-4 font-semibold text-white hover:text-white"
                >
                    <StoreIcon name="bag" class="size-[21px]" />
                    سبد خرید
                    <span
                        class="text-primary-ink grid h-5 min-w-5 place-items-center rounded-md bg-surface px-1 text-xs font-bold"
                    >
                        {{ formatNumber(cart.itemsCount) }}
                    </span>
                </RouterLink>
            </div>
        </div>
    </header>

    <nav aria-label="منوی اصلی" class="hidden border-b border-line bg-surface lg:block">
        <div class="max-w-store mx-auto flex h-[50px] items-center gap-[26px] px-6 text-sm">
            <RouterLink :to="paths.categories" class="text-ink flex items-center gap-2 font-semibold">
                <StoreIcon name="menu" class="size-[18px]" />
                دسته‌بندی کالاها
            </RouterLink>
            <RouterLink :to="paths.deals" class="font-semibold text-primary">پیشنهاد شگفت‌انگیز</RouterLink>
            <MenuLinkItem
                v-for="(link, index) in menu"
                :key="index"
                :link="link"
                class="text-muted hover:text-primary"
            />
            <span v-if="store.deliveryNote" class="text-muted ms-auto text-[13px]">{{ store.deliveryNote }}</span>
        </div>
    </nav>
</template>
