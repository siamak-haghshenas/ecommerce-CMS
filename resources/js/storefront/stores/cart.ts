import { defineStore } from 'pinia';
import { ref } from 'vue';
import { cartApi } from '../api';
import { tokens } from '../http/client';
import type { Cart } from '../types';

/** The cart badge and quick adds. Guests' carts are found by the token the first add returns. */
export const useCartStore = defineStore('store-cart', () => {
    const itemsCount = ref(0);
    const adding = ref<number | null>(null);

    function apply(cart: Cart, cartToken?: string): void {
        itemsCount.value = cart.items_count;

        if (cartToken !== undefined) {
            tokens.setCart(cartToken);
        }
    }

    async function load(): Promise<void> {
        // Without a customer or a guest token there is no cart to read.
        if (tokens.customer() === null && tokens.cart() === null) {
            return;
        }

        try {
            const response = await cartApi.show();
            apply(response.data);
        } catch {
            // The badge simply stays empty.
        }
    }

    /** Throws the API error so the caller can show its message. */
    async function add(variantId: number): Promise<void> {
        adding.value = variantId;

        try {
            const response = await cartApi.add(variantId);
            apply(response.data, response.meta.cart_token);
        } finally {
            adding.value = null;
        }
    }

    return { itemsCount, adding, load, add };
});
