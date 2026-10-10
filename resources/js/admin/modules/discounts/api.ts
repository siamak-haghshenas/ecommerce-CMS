import { http } from '@/shared/http/client';
import type { Paginated, Resource } from '@/shared/types/api';
import type { Promotion, PromotionFilters, SavePromotionPayload } from './types';

export const promotionsApi = {
    /** Permission: discounts.view */
    list: async (filters: PromotionFilters = {}): Promise<Paginated<Promotion>> =>
        (await http.get<Paginated<Promotion>>('/admin/promotions', { params: filters })).data,

    /** Permission: discounts.view. With `products_count`. */
    show: async (id: number): Promise<Promotion> =>
        (await http.get<Resource<Promotion>>(`/admin/promotions/${id}`)).data.data,

    /** Permission: discounts.manage. Saving reprices the catalog. */
    create: async (payload: SavePromotionPayload): Promise<Promotion> =>
        (await http.post<Resource<Promotion>>('/admin/promotions', payload)).data.data,

    /** Permission: discounts.manage. Saving reprices the catalog. */
    update: async (id: number, payload: SavePromotionPayload): Promise<Promotion> =>
        (await http.put<Resource<Promotion>>(`/admin/promotions/${id}`, payload)).data.data,

    /** Permission: discounts.manage */
    remove: async (id: number): Promise<void> => {
        await http.delete(`/admin/promotions/${id}`);
    },

    /** Permission: discounts.manage. How many products the (unsaved) form would apply to. */
    preview: async (payload: SavePromotionPayload): Promise<number> =>
        (await http.post<Resource<{ products_count: number }>>('/admin/promotions/preview', payload)).data.data
            .products_count,
};

/** The payload of a saved promotion, e.g. to switch it on or off without opening it. */
export function payloadOf(promotion: Promotion): SavePromotionPayload {
    const ids = (items: { id: number }[]): number[] => items.map((item) => item.id);

    return {
        name: promotion.name,
        type: promotion.type,
        value: promotion.value,
        max_discount: promotion.max_discount,
        scope: promotion.scope,
        product_ids: ids(promotion.products),
        category_ids: ids(promotion.categories),
        brand_ids: ids(promotion.brands),
        tag_ids: ids(promotion.tags),
        excluded_product_ids: ids(promotion.excluded_products),
        starts_at: promotion.starts_at,
        ends_at: promotion.ends_at,
        is_active: promotion.is_active,
    };
}
