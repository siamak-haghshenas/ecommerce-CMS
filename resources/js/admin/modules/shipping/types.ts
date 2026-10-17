/** How a method's fee is worked out (App\Enums\ShippingMethodType). */
export type ShippingType = 'weight' | 'volume' | 'courier';

/** A weight (grams) or volume (cm³) range: `start` included, `end` excluded (null: no upper limit). Fees are Rials. */
export interface ShippingRate {
    start: number;
    end: number | null;
    fee: number;
}

export interface ShippingCity {
    id: number;
    name: string;
    province: { id: number; name: string };
    /** Null: the method's default fee. */
    fee: number | null;
}

export interface ShippingMethod {
    id: number;
    name: string;
    description: string | null;
    type: ShippingType;
    fee_on_delivery: boolean;
    default_fee: number | null;
    packaging_weight_grams: number | null;
    min_order_amount: number | null;
    free_over_amount: number | null;
    delivery_days_min: number | null;
    delivery_days_max: number | null;
    is_active: boolean;
    position: number;
    rates: ShippingRate[];
    cities: ShippingCity[];
    created_at: string | null;
    updated_at: string | null;
}

export interface ShippingMethodList {
    data: ShippingMethod[];
    meta: { unmeasured_products: { missing_weight: number; missing_dimensions: number } };
}

export interface SaveShippingMethodPayload {
    name: string;
    description: string | null;
    type: ShippingType;
    fee_on_delivery: boolean;
    default_fee: number | null;
    packaging_weight_grams: number | null;
    min_order_amount: number | null;
    free_over_amount: number | null;
    delivery_days_min: number | null;
    delivery_days_max: number | null;
    is_active: boolean;
    rates: ShippingRate[] | null;
    cities: { id: number; fee: number | null }[] | null;
}

export interface Province {
    id: number;
    name: string;
    cities: { id: number; name: string }[];
}

export type ShippingUnavailableReason =
    'inactive' | 'city_not_served' | 'below_min_order' | 'missing_weight' | 'missing_dimensions' | 'out_of_range';

/** The test box's answer. Amounts are Rials; weights grams; volumes cm³. */
export interface ShippingSimulation {
    lines: {
        variant_id: number;
        quantity: number;
        unit_price: number;
        unit_weight_grams: number | null;
        uses_default_weight: boolean;
        unit_volume_cm3: number | null;
    }[];
    subtotal: number;
    coupon_discount: number;
    coupon_problem: 'min_order' | 'no_eligible_items' | null;
    free_shipping_coupon: boolean;
    items_total: number;
    weight_grams: number | null;
    volume_cm3: number | null;
    shipping: {
        available: boolean;
        reason: ShippingUnavailableReason | null;
        measure: number | null;
        range: ShippingRate | null;
        original_fee: number | null;
        fee: number | null;
        free_reason: 'free_over' | 'coupon' | null;
        fee_on_delivery: boolean;
        payable_now: number | null;
        collect_on_delivery: number | null;
        remaining_for_free: number | null;
    };
}
