import { http } from '@/shared/http/client';
import type { Paginated, Resource } from '@/shared/types/api';
import type { ProductFilters } from '../products/types';
import type {
    Promotion,
    PromotionPayload,
    PromotionPickerProduct,
    SaleEvent,
    SaleEventDetail,
    SaleEventFilters,
    SaleEventPreview,
    SaleEventVariant,
    SaveSaleEventPayload,
} from './types';

export const promotionsApi = {
    /** Permission: discounts.view. Every row, grouped by product. */
    list: async (): Promise<Promotion[]> => (await http.get<Resource<Promotion[]>>('/admin/promotions')).data.data,

    /** Permission: discounts.manage. New and changed rows plus removed ids, all or nothing; returns every row. */
    save: async (rows: PromotionPayload[], deleted: number[]): Promise<Promotion[]> =>
        (await http.put<Resource<Promotion[]>>('/admin/promotions', { promotions: rows, deleted })).data.data,

    /** Permission: discounts.manage. Products with their enabled variants for "Add product". */
    variants: async (
        filters: ProductFilters & { page?: number; per_page?: number },
    ): Promise<Paginated<PromotionPickerProduct>> =>
        (await http.get<Paginated<PromotionPickerProduct>>('/admin/promotions/variants', { params: filters })).data,
};

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
            max_per_order: item.max_per_order,
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
