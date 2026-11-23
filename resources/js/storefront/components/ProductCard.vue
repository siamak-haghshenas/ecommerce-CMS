<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from '../composables/useToast';
import { errorMessage } from '../http/client';
import { paths } from '../links';
import { useCartStore } from '../stores/cart';
import type { ProductCard } from '../types';
import PriceTag from './PriceTag.vue';
import StoreIcon from './StoreIcon.vue';

const props = defineProps<{ product: ProductCard }>();

const cart = useCartStore();
const router = useRouter();
const toast = useToast();

const link = computed(() => paths.product(props.product.slug));
const busy = computed(() => props.product.variant_id !== null && cart.adding === props.product.variant_id);

/** A simple product goes straight in the cart; one with options needs its page to choose. */
async function addToCart(): Promise<void> {
    if (props.product.variant_id === null) {
        await router.push(link.value);

        return;
    }

    try {
        await cart.add(props.product.variant_id);
        toast.show(`«${props.product.name}» به سبد خرید اضافه شد.`);
    } catch (error) {
        toast.show(errorMessage(error), 'error');
    }
}
</script>

<template>
    <article class="flex flex-col gap-2.5 rounded-xl bg-surface p-3 lg:p-4">
        <RouterLink :to="link" class="block h-[140px] lg:h-[170px]" tabindex="-1" aria-hidden="true">
            <img
                v-if="product.image"
                :src="product.image.medium"
                :alt="product.image.alt ?? product.name"
                loading="lazy"
                class="size-full object-contain"
                :class="{ 'opacity-45': !product.in_stock }"
            />
            <span v-else class="block size-full rounded-[10px] bg-bg" />
        </RouterLink>
        <h3 class="m-0 line-clamp-2 min-h-[3.2em] text-sm leading-[1.6] font-medium">
            <RouterLink :to="link" class="text-ink hover:text-primary">{{ product.name }}</RouterLink>
        </h3>
        <div class="mt-auto flex items-center justify-between gap-2">
            <PriceTag
                v-if="product.in_stock"
                :rials="product.price.min"
                :from="product.price.min !== product.price.max"
                class="text-[15px] lg:text-base"
            />
            <span v-else class="text-muted text-sm font-semibold">ناموجود</span>
            <button
                v-if="product.in_stock"
                type="button"
                :aria-label="
                    product.has_variants ? `انتخاب گزینه‌های ${product.name}` : `افزودن ${product.name} به سبد`
                "
                :disabled="busy"
                class="grid size-11 shrink-0 place-items-center rounded-[10px] border border-line bg-surface text-primary disabled:opacity-50"
                @click="addToCart"
            >
                <StoreIcon name="plus" :stroke-width="2.2" class="size-5" />
            </button>
        </div>
    </article>
</template>
