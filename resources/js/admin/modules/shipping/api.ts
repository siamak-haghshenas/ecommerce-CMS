import { http } from '@/shared/http/client';
import type { Resource } from '@/shared/types/api';
import type {
    Province,
    SaveShippingMethodPayload,
    ShippingMethod,
    ShippingMethodList,
    ShippingSimulation,
} from './types';

export const shippingApi = {
    /** Permission: shipping.view. Every method in the checkout order (not paginated), with counts of products shipping can't measure. */
    list: async (): Promise<ShippingMethodList> => (await http.get<ShippingMethodList>('/admin/shipping-methods')).data,

    /** Permission: shipping.view. */
    show: async (id: number): Promise<ShippingMethod> =>
        (await http.get<Resource<ShippingMethod>>(`/admin/shipping-methods/${id}`)).data.data,

    /** Permission: shipping.manage. New methods go last. */
    create: async (payload: SaveShippingMethodPayload): Promise<ShippingMethod> =>
        (await http.post<Resource<ShippingMethod>>('/admin/shipping-methods', payload)).data.data,

    /** Permission: shipping.manage. */
    update: async (id: number, payload: SaveShippingMethodPayload): Promise<ShippingMethod> =>
        (await http.put<Resource<ShippingMethod>>(`/admin/shipping-methods/${id}`, payload)).data.data,

    /** Permission: shipping.manage. A soft delete: orders keep its name and fee. */
    remove: async (id: number): Promise<void> => {
        await http.delete(`/admin/shipping-methods/${id}`);
    },

    /** Permission: shipping.manage. `ids`: every method, in checkout order; 409 `shipping_method_list_changed` otherwise. */
    reorder: async (ids: number[]): Promise<ShippingMethod[]> =>
        (await http.put<Resource<ShippingMethod[]>>('/admin/shipping-methods/order', { ids })).data.data,

    /** Permission: shipping.manage. The unsaved method priced for sample items; nothing is saved. */
    simulate: async (body: {
        method: SaveShippingMethodPayload;
        items: { variant_id: number; quantity: number }[];
        city_id: number | null;
        coupon_code: string | null;
    }): Promise<ShippingSimulation> =>
        (await http.post<Resource<ShippingSimulation>>('/admin/shipping-methods/simulate', body)).data.data,

    /** Permission: shipping.view. Every province with its cities. */
    provinces: async (): Promise<Province[]> => (await http.get<Resource<Province[]>>('/admin/provinces')).data.data,
};

/** The payload of a saved method, e.g. to switch it on or off from the list. */
export function shippingPayloadOf(method: ShippingMethod): SaveShippingMethodPayload {
    return {
        name: method.name,
        description: method.description,
        type: method.type,
        fee_on_delivery: method.fee_on_delivery,
        default_fee: method.default_fee,
        packaging_weight_grams: method.packaging_weight_grams,
        min_order_amount: method.min_order_amount,
        free_over_amount: method.free_over_amount,
        delivery_days_min: method.delivery_days_min,
        delivery_days_max: method.delivery_days_max,
        is_active: method.is_active,
        rates: method.type === 'courier' ? null : method.rates,
        cities: method.type === 'courier' ? method.cities.map((city) => ({ id: city.id, fee: city.fee })) : null,
    };
}
