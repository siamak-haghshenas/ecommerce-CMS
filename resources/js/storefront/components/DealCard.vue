<script setup lang="ts">
import { computed } from 'vue';
import { timeLeft } from '../deals';
import { paths } from '../links';
import { formatNumber } from '../money';
import type { ProductCard } from '../types';
import PriceTag from './PriceTag.vue';

const props = defineProps<{ product: ProductCard; now: number }>();

const left = computed(() => timeLeft(props.product.discount_ends_at, props.now));
const discounted = computed(() => props.product.regular_price.min > props.product.price.min);
</script>

<template>
    <RouterLink
        :to="paths.product(product.slug)"
        class="text-ink hover:text-ink flex flex-col gap-1.5 rounded-xl bg-surface p-3 lg:gap-2 lg:p-3.5"
    >
        <img
            v-if="product.image"
            :src="product.image.medium"
            :alt="product.image.alt ?? product.name"
            loading="lazy"
            class="h-[110px] w-full object-contain lg:h-[140px]"
        />
        <span v-else class="h-[110px] w-full rounded-[10px] bg-bg lg:h-[140px]" aria-hidden="true" />
        <span class="line-clamp-2 min-h-[3.2em] text-[12.5px] leading-[1.6] lg:text-[13.5px]">{{ product.name }}</span>
        <span class="flex items-center justify-between gap-2">
            <span
                v-if="product.discount_percent > 0"
                class="rounded-md bg-primary px-1.5 text-[11px] font-semibold text-white lg:px-2 lg:text-xs"
            >
                {{ formatNumber(product.discount_percent) }}٪
            </span>
            <PriceTag
                :rials="product.price.min"
                :from="product.price.min !== product.price.max"
                class="ms-auto text-[13.5px] lg:text-base"
            />
        </span>
        <s v-if="discounted" class="text-muted-2 self-end text-[12.5px]">
            <span class="sr-only">قیمت قبلی: </span>
            <PriceTag :rials="product.regular_price.min" class="font-normal" />
        </s>
        <template v-if="left">
            <span class="mt-auto block h-1 overflow-hidden rounded-sm bg-bg" aria-hidden="true">
                <span class="block h-full bg-primary" :style="{ width: `${left.percent}%` }" />
            </span>
            <small class="text-muted text-[11px] lg:text-xs">{{ left.label }}</small>
        </template>
    </RouterLink>
</template>
