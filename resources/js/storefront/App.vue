<script setup lang="ts">
import { onMounted } from 'vue';
import MobileTabBar from './components/MobileTabBar.vue';
import StoreFooter from './components/StoreFooter.vue';
import StoreHeader from './components/StoreHeader.vue';
import StoreToasts from './components/StoreToasts.vue';
import { useCartStore } from './stores/cart';
import { useSessionStore } from './stores/session';

const session = useSessionStore();
const cart = useCartStore();

onMounted(async () => {
    await session.load();
    await cart.load();
});
</script>

<template>
    <div class="flex min-h-screen flex-col pb-20 lg:pb-0">
        <StoreHeader />
        <main id="content" class="flex-1">
            <RouterView />
        </main>
        <StoreFooter />
    </div>
    <MobileTabBar />
    <StoreToasts />
</template>
