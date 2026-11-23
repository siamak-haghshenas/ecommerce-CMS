<script setup lang="ts">
import ProductCard from '../components/ProductCard.vue';
import { paths } from '../links';
import type { ProductCard as ProductCardData } from '../types';

defineProps<{ products: ProductCardData[] }>();
</script>

<template>
    <section aria-labelledby="new-arrivals-title">
        <div class="mb-3.5 flex items-center justify-between">
            <h2 id="new-arrivals-title" class="m-0 text-[17px] font-semibold lg:text-[22px]">تازه‌ها</h2>
            <RouterLink :to="`${paths.categories}?sort=newest`" class="text-sm font-medium lg:text-[15px]">
                مشاهده همه
            </RouterLink>
        </div>
        <ul class="m-0 grid list-none grid-cols-2 gap-2.5 p-0 md:grid-cols-3 lg:grid-cols-5 lg:gap-3">
            <li v-for="product in products" :key="product.id" class="flex">
                <ProductCard :product="product" class="w-full" />
            </li>
        </ul>
    </section>
</template>
