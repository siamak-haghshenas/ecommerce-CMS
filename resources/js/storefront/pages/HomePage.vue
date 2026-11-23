<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { catalogApi } from '../api';
import { home, type HeroSlide, type PromoCard } from '../content';
import CategoryCircles from '../home/CategoryCircles.vue';
import DealsStrip from '../home/DealsStrip.vue';
import HeroSlider from '../home/HeroSlider.vue';
import NewArrivals from '../home/NewArrivals.vue';
import PromoCards from '../home/PromoCards.vue';
import { paths } from '../links';
import type { CategoryNode, ProductCard, SaleEventSummary } from '../types';

const loading = ref(true);
const categories = ref<CategoryNode[]>([]);
const saleEvents = ref<SaleEventSummary[]>([]);
const deals = ref<ProductCard[]>([]);
const newArrivals = ref<ProductCard[]>([]);

/** Sale events with a banner lead the slider; the slides from content.ts follow. */
const slides = computed<HeroSlide[]>(() => [
    ...saleEvents.value
        .filter((event) => event.banner !== null)
        .map((event) => ({
            badge: event.status === 'upcoming' ? 'به‌زودی' : 'فروش ویژه',
            title: event.title,
            text: null,
            cta: 'مشاهده',
            to: paths.saleEvent(event.slug),
            image: event.banner?.large ?? event.banner?.medium ?? null,
        })),
    ...home.heroSlides,
]);

const fallbackImage = computed(() => {
    const image = deals.value.find((product) => product.image !== null)?.image;

    return image ? { src: image.medium, alt: image.alt ?? '' } : null;
});

const promoCards = computed<PromoCard[]>(() =>
    home.promoCards.length > 0
        ? home.promoCards
        : categories.value.slice(0, 2).map((category) => ({
              title: category.name,
              text:
                  category.children
                      .slice(0, 2)
                      .map((child) => child.name)
                      .join(' و ') || null,
              cta: 'مشاهده',
              to: paths.category(category.slug),
              image: category.image?.medium ?? null,
          })),
);

const circles = computed(() => categories.value.slice(0, home.categoriesCount));

/** One failed section never blanks the others. */
async function settle<T>(request: Promise<T>, fallback: T): Promise<T> {
    try {
        return await request;
    } catch {
        return fallback;
    }
}

onMounted(async () => {
    const [categoryTree, events, dealPage, newPage] = await Promise.all([
        settle(catalogApi.categories(), []),
        settle(catalogApi.saleEvents(), []),
        settle(catalogApi.products({ on_sale: 1, in_stock: 1, per_page: home.dealsCount }), null),
        settle(catalogApi.products({ sort: 'newest', per_page: home.newArrivalsCount }), null),
    ]);

    categories.value = categoryTree;
    saleEvents.value = events;
    deals.value = dealPage?.data ?? [];
    newArrivals.value = newPage?.data ?? [];
    loading.value = false;
});
</script>

<template>
    <div class="flex flex-col gap-4 pt-3 lg:gap-7 lg:pt-5">
        <div class="max-w-store mx-auto grid w-full gap-2.5 px-4 lg:grid-cols-12 lg:gap-4 lg:px-6">
            <HeroSlider :slides="slides" :fallback-image="fallbackImage" class="lg:col-span-8" />
            <PromoCards v-if="promoCards.length > 0" :cards="promoCards" class="lg:col-span-4" />
            <div v-else-if="loading" class="hidden rounded-2xl bg-surface lg:col-span-4 lg:block" aria-hidden="true" />
        </div>

        <div v-if="loading" class="max-w-store mx-auto w-full px-4 lg:px-6" aria-busy="true">
            <div class="h-[200px] animate-pulse rounded-2xl bg-surface lg:h-[170px]" />
            <span class="sr-only">در حال بارگذاری…</span>
        </div>

        <template v-else>
            <div v-if="circles.length > 0" class="max-w-store mx-auto w-full px-4 lg:px-6">
                <CategoryCircles :categories="circles" />
            </div>

            <div v-if="deals.length > 0" class="max-w-store mx-auto w-full lg:px-6">
                <DealsStrip :products="deals" />
            </div>

            <div v-if="newArrivals.length > 0" class="max-w-store mx-auto w-full px-4 lg:px-6">
                <NewArrivals :products="newArrivals" />
            </div>
        </template>
    </div>
</template>
