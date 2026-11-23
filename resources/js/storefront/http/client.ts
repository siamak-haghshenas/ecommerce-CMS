import axios from 'axios';
import { readStorage, writeStorage } from '@/shared/utils/storage';
import { rememberCurrency } from '../money';

const CUSTOMER_TOKEN_KEY = 'store_customer_token';
const CART_TOKEN_KEY = 'store_cart_token';

/**
 * The storefront talks to the Storefront API like any external client: no cookies, the customer's
 * Bearer token and the guest cart's X-Cart-Token header, always in Farsi.
 */
export const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        Accept: 'application/json',
        'Accept-Language': 'fa',
    },
});

export const tokens = {
    customer: (): string | null => readStorage('local', CUSTOMER_TOKEN_KEY),
    setCustomer: (token: string | null): void => writeStorage('local', CUSTOMER_TOKEN_KEY, token),
    cart: (): string | null => readStorage('local', CART_TOKEN_KEY),
    setCart: (token: string | null): void => writeStorage('local', CART_TOKEN_KEY, token),
};

api.interceptors.request.use((config) => {
    const customer = tokens.customer();
    const cart = tokens.cart();

    if (customer !== null) {
        config.headers.set('Authorization', `Bearer ${customer}`);
    }

    if (cart !== null) {
        config.headers.set('X-Cart-Token', cart);
    }

    return config;
});

api.interceptors.response.use((response) => {
    const currency = response.data?.meta?.currency;

    if (currency) {
        rememberCurrency(currency);
    }

    return response;
});

/** The API's error message (already in Farsi), or a generic one. */
export function errorMessage(error: unknown): string {
    if (axios.isAxiosError(error)) {
        const message = error.response?.data?.message;

        if (typeof message === 'string' && message !== '') {
            return message;
        }

        if (error.response === undefined) {
            return 'اتصال برقرار نشد. اینترنت خود را بررسی کنید.';
        }
    }

    return 'مشکلی پیش آمد. دوباره تلاش کنید.';
}

export function isUnauthenticated(error: unknown): boolean {
    return axios.isAxiosError(error) && error.response?.status === 401;
}
