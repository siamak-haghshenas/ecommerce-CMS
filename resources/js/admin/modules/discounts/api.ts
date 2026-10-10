import { http } from '@/shared/http/client';
import type { Paginated, Resource } from '@/shared/types/api';
import type {
    Promotion,
    PromotionFilters,
    SaleEvent,
    SaleEventDetail,
    SaleEventFilters,
    SaleEventPreview,
    SaleEventVariant,
    SavePromotionPayload,
    SaveSaleEventPayload,
} from './types';

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

export const saleEventsApi = {
    /** Permission: discounts.view. The whole list in priority order (not paginated). */
    list: async (filters: SaleEventFilters = {}): Promise<SaleEvent[]> =>
        (await http.get<Resource<SaleEvent[]>>('/admin/sale-events', { params: filters })).data.data,

    /** Permission: discounts.view. With every variant and the events that override it. */
    show: async (id: number): Promise<SaleEventDetail> =>
        (await http.get<Resource<SaleEventDetail>>(`/admin/sale-events/${id}`)).data.data,

    /** Permission: discounts.manage. Saving reprices the event's products. */
    create: async (payload: SaveSaleEventPayload): Promise<SaleEventDetail> =>
        (await http.post<Resource<SaleEventDetail>>('/admin/sale-events', payload)).data.data,

    /** Permission: discounts.manage. Saving reprices the products it had and has. */
    update: async (id: number, payload: SaveSaleEventPayload): Promise<SaleEventDetail> =>
        (await http.put<Resource<SaleEventDetail>>(`/admin/sale-events/${id}`, payload)).data.data,

    /** Permission: discounts.manage. A hard delete. */
    remove: async (id: number): Promise<void> => {
        await http.delete(`/admin/sale-events/${id}`);
    },

    /** Permission: discounts.manage. `ids`: every event, highest priority first; 409 `sale_event_list_changed` otherwise. */
    reorder: async (ids: number[]): Promise<SaleEvent[]> =>
        (await http.put<Resource<SaleEvent[]>>('/admin/sale-events/order', { ids })).data.data,

    /** Permission: discounts.manage. The enabled variants of these products (≤ 500), in the order given. */
    variants: async (productIds: number[]): Promise<SaleEventVariant[]> =>
        (await http.post<Resource<SaleEventVariant[]>>('/admin/sale-events/variants', { product_ids: productIds })).data
            .data,

    /** Permission: discounts.view. A token for the storefront's preview of the saved landing page. */
    preview: async (id: number): Promise<SaleEventPreview> =>
        (await http.post<Resource<SaleEventPreview>>(`/admin/sale-events/${id}/preview`)).data.data,
};

/** The payload of a saved sale event, e.g. to switch it on or off from the list. */
export function saleEventPayloadOf(event: SaleEventDetail): SaveSaleEventPayload {
    return {
        name: event.name,
        starts_at: event.starts_at,
        ends_at: event.ends_at,
        is_active: event.is_active,
        items: event.items.map((item) => ({
            variant_id: item.variant.id,
            discount_type: item.discount_type,
            discount_value: item.discount_value,
            starts_at: item.starts_at,
            ends_at: item.ends_at,
        })),
        has_landing_page: event.has_landing_page,
        slug: event.slug,
        landing_title: event.landing_title,
        landing_description: event.landing_description,
        banner_id: event.banner?.id ?? null,
        meta_title: event.meta_title,
        meta_description: event.meta_description,
        focus_keyphrase: event.focus_keyphrase,
        robots_noindex: event.robots_noindex,
        canonical_url: event.canonical_url,
        og_title: event.og_title,
        og_description: event.og_description,
        og_image_id: event.og_image?.id ?? null,
    };
}
