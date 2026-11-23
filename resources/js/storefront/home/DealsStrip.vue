<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
import DealCard from '../components/DealCard.vue';
import StoreIcon from '../components/StoreIcon.vue';
import { paths } from '../links';
import type { ProductCard } from '../types';

defineProps<{ products: ProductCard[] }>();

// Countdown labels change by the minute.
const now = ref(Date.now());
const timer = setInterval(() => {
    now.value = Date.now();
}, 30_000);

onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <section
        aria-labelledby="deals-title"
        class="bg-ink flex flex-col gap-3 py-4 ps-4 lg:flex-row lg:items-stretch lg:gap-4 lg:rounded-2xl lg:p-5"
    >
        <div
            class="flex items-center justify-between pe-4 text-white lg:w-[180px] lg:shrink-0 lg:flex-col lg:items-start lg:justify-center lg:gap-3 lg:px-2"
        >
            <StoreIcon name="bolt" filled class="hidden size-11 text-primary lg:block" />
            <h2
                id="deals-title"
                class="m-0 flex items-center gap-1.5 text-[17px] leading-[1.3] font-bold lg:text-[26px]"
            >
                <StoreIcon name="bolt" filled class="size-[22px] text-primary lg:hidden" />
                پیشنهاد شگفت‌انگیز
            </h2>
            <p class="m-0 hidden text-[13px] text-line-strong lg:block">
                هر پیشنهاد زمان خودش را دارد. نوار قرمز زیر هر کالا نشان می‌دهد چقدر وقت مانده.
            </p>
            <RouterLink :to="paths.deals" class="text-primary-on-dark text-xs font-semibold lg:text-sm">
                همه پیشنهادها
            </RouterLink>
        </div>
        <ul
            class="m-0 flex flex-1 snap-x scrollbar-none list-none gap-2 overflow-x-auto p-0 pe-4 lg:grid lg:grid-cols-5 lg:gap-2.5 lg:overflow-visible lg:pe-0"
        >
            <li v-for="product in products" :key="product.id" class="flex w-[150px] shrink-0 snap-start lg:w-auto">
                <DealCard :product="product" :now="now" class="w-full" />
            </li>
        </ul>
    </section>
</template>
