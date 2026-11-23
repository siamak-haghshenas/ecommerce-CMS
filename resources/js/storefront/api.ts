import { api } from './http/client';
import type { Cart, CategoryNode, Customer, MenuItem, Paginated, ProductCard, SaleEventSummary } from './types';

export interface ProductQuery {
    q?: string;
    category?: string;
    on_sale?: 1;
    in_stock?: 1;
    sort?: 'newest' | 'price_asc' | 'price_desc' | 'name';
    per_page?: number;
}

export const catalogApi = {
    async products(query: ProductQuery): Promise<Paginated<ProductCard>> {
        return (await api.get<Paginated<ProductCard>>('products', { params: query })).data;
    },
    async categories(): Promise<CategoryNode[]> {
        return (await api.get<{ data: CategoryNode[] }>('categories')).data.data;
    },
    async saleEvents(): Promise<SaleEventSummary[]> {
        return (await api.get<{ data: SaleEventSummary[] }>('sale-events')).data.data;
    },
};

export const contentApi = {
    async menu(location: 'header' | 'footer'): Promise<MenuItem[]> {
        return (await api.get<{ data: MenuItem[] }>(`menus/${location}`)).data.data;
    },
};

export const accountApi = {
    async me(): Promise<Customer> {
        return (await api.get<{ data: Customer }>('auth/me')).data.data;
    },
};

interface CartResponse {
    data: Cart;
    meta: { cart_token?: string };
}

export const cartApi = {
    async show(): Promise<CartResponse> {
        return (await api.get<CartResponse>('cart')).data;
    },
    async add(variantId: number, quantity = 1): Promise<CartResponse> {
        return (await api.post<CartResponse>('cart/items', { variant_id: variantId, quantity })).data;
    },
};
