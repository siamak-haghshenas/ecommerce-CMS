import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { accountApi } from '../api';
import { isUnauthenticated, tokens } from '../http/client';
import type { Customer } from '../types';

/** The logged-in customer (Bearer token in localStorage), or a guest. */
export const useSessionStore = defineStore('store-session', () => {
    const customer = ref<Customer | null>(null);
    const isLoggedIn = computed(() => customer.value !== null);

    async function load(): Promise<void> {
        if (tokens.customer() === null) {
            return;
        }

        try {
            customer.value = await accountApi.me();
        } catch (error) {
            // An expired or revoked token: carry on as a guest.
            if (isUnauthenticated(error)) {
                tokens.setCustomer(null);
            }
        }
    }

    return { customer, isLoggedIn, load };
});
