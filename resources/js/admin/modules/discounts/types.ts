import type { Paginated } from '@/shared/types/api';
import type { MediaPicture } from '../media/types';

/** A percentage or a fixed amount off each unit. */
export type DiscountType = 'percent' | 'fixed';

export type SaleEventState = 'active' | 'scheduled' | 'expired' | 'disabled';

/** A higher-priority event that takes a variant over while their dates overlap. */
export interface OverridingEvent {
    id: number;
    name: string;
    starts_at: string;
    ends_at: string;
}

/** A variant in a sale event, with its own discount. Amounts are Rials. */
export interface SaleEventItem {
    id: number;
    discount_type: DiscountType;
    /** A percentage (1–90) or Rials off each unit. */
    discount_value: number;
    /** The most units one order may hold at the event's price; null: no limit. */
    max_per_order: number | null;
    /** Null: the event's dates. */
    starts_at: string | null;
    ends_at: string | null;
    overridden_by: OverridingEvent[];
    variant: {
        id: number;
        sku: string;
        /** Option values in the product's option order ("M · Red"); empty for a simple product. */
        label: string;
        price: number;
        sale_price: number | null;
        current_price: number;
        discount_source: 'sale' | 'sale_event' | 'promotion' | null;
        sale_event_id: number | null;
        promotion_id: number | null;
        is_enabled: boolean;
        is_deleted: boolean;
    };
    product: {
        id: number;
        name: string;
        type: 'simple' | 'variable';
        image: string | null;
        is_deleted: boolean;
    };
}

/** A row of the list; the detail view adds the items, the landing page and its SEO fields. */
export interface SaleEvent {
    id: number;
    name: string;
    starts_at: string;
    ends_at: string;
    is_active: boolean;
    state: SaleEventState;
    /** Priority: 0 is the highest. */
    position: number;
    has_landing_page: boolean;
    slug: string | null;
    items_count?: number;
    products_count?: number;
    seo_score: number | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface SaleEventDetail extends SaleEvent {
    items: SaleEventItem[];
    landing_title: string | null;
    landing_description: string | null;
    banner: MediaPicture | null;
    meta_title: string | null;
    meta_description: string | null;
    focus_keyphrase: string | null;
    readability_score: number | null;
    robots_noindex: boolean;
    canonical_url: string | null;
    og_title: string | null;
    og_description: string | null;
    og_image: MediaPicture | null;
}

export interface SaleEventFilters {
    q?: string;
    status?: SaleEventState;
}

export interface SaleEventItemPayload {
    variant_id: number;
    discount_type: DiscountType;
    discount_value: number;
    max_per_order: number | null;
    starts_at: string | null;
    ends_at: string | null;
}

export interface SaveSaleEventPayload {
    name: string;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
    items: SaleEventItemPayload[];
    has_landing_page: boolean;
    slug: string | null;
    landing_title: string | null;
    landing_description: string | null;
    banner_id: number | null;
    meta_title: string | null;
    meta_description: string | null;
    focus_keyphrase: string | null;
    robots_noindex: boolean;
    canonical_url: string | null;
    og_title: string | null;
    og_description: string | null;
    og_image_id: number | null;
}

export interface SaleEventPreview {
    token: string;
    /** Null when the store has no storefront preview address. */
    url: string | null;
    expires_at: string;
}

/** A variant with its product, as "Add products" adds it (the same shape as an item's). */
export type SaleEventVariant = Pick<SaleEventItem, 'variant' | 'product'>;

// ---- promotions ----

export type PromotionState = 'scheduled' | 'active' | 'sold_out' | 'ended';

/** One SKU's discount with its own dates and limits, with its variant and product. Amounts are Rials. */
export interface Promotion extends SaleEventVariant {
    id: number;
    discount_type: DiscountType;
    /** A percentage (1–90) or Rials off each unit. */
    discount_value: number;
    /** The most units one order may hold at this price; null: no limit. */
    max_per_order: number | null;
    /** How many units are sold at this price in all; null: no limit. */
    quantity_limit: number | null;
    /** Counted by orders. */
    sold_count: number;
    starts_at: string;
    ends_at: string;
    state: PromotionState;
}

export interface PromotionPayload {
    /** Null: a new row. */
    id: number | null;
    variant_id: number;
    discount_type: DiscountType;
    discount_value: number;
    max_per_order: number | null;
    quantity_limit: number | null;
    starts_at: string | null;
    ends_at: string | null;
}

/** A product in "Add product", with its enabled variants. */
export interface PromotionPickerProduct {
    id: number;
    name: string;
    type: 'simple' | 'variable';
    status: 'draft' | 'published' | 'archived';
    image: string | null;
    variants: {
        id: number;
        sku: string;
        label: string;
        price: number;
        current_price: number;
        in_stock: boolean;
        /** Null when the product doesn't track stock. */
        stock_quantity: number | null;
        /** It has a row that hasn't ended: a new one must not overlap it. */
        has_promotion: boolean;
    }[];
}

// ---- coupons ----

/** Percent or Rials off the eligible items, or free shipping. */
export type CouponType = 'percent' | 'fixed' | 'free_shipping';

export type CouponState = 'active' | 'scheduled' | 'used_up' | 'expired' | 'disabled';

/** Something a coupon is linked to (a category, brand, tag or product), with its name for display. */
export interface CouponLink {
    id: number;
    name: string;
}

/** The items a coupon applies to when `applies_to` is `selected`; categories include their sub-categories. */
export interface CouponTargets {
    categories: CouponLink[];
    brands: CouponLink[];
    tags: CouponLink[];
    products: CouponLink[];
}

export type CouponTargetKind = keyof CouponTargets;

/** A customer allowed to use a coupon (`customer_scope` `selected`). */
export interface CouponCustomer {
    id: number;
    name: string;
    phone_number: string;
    /** Orders of this customer that used the coupon. */
    used_count: number;
}

/** A row of the list; the detail view adds the linked items and customers. Amounts are Rials. */
export interface Coupon {
    id: number;
    /** Uppercase Latin letters and digits. */
    code: string;
    /** For staff only. */
    name: string;
    type: CouponType;
    /** A percentage (1–90) or Rials off the order; null for free shipping. */
    value: number | null;
    /** The most Rials a percent coupon takes off; null: no cap. */
    max_discount: number | null;
    /** The least the eligible items must add up to; null: no minimum. */
    min_order_amount: number | null;
    applies_to: 'all' | 'selected';
    targets_count: Record<CouponTargetKind, number>;
    excluded_count: number;
    /** Items priced by a sale, sale event or promotion don't count. */
    exclude_discounted: boolean;
    customer_scope: 'all' | 'selected';
    customers_count: number;
    /** Null: unlimited. */
    usage_limit: number | null;
    per_customer_limit: number | null;
    /** Counted by orders. */
    used_count: number;
    first_order_only: boolean;
    /** Null: from when it was saved. */
    starts_at: string | null;
    /** Null: never ends. */
    ends_at: string | null;
    is_active: boolean;
    state: CouponState;
    created_at: string | null;
    updated_at: string | null;
}

export interface CouponDetail extends Coupon {
    targets: CouponTargets;
    excluded_products: CouponLink[];
    customers: CouponCustomer[];
}

export interface CouponFilters {
    q?: string;
    status?: CouponState;
    page?: number;
    per_page?: number;
}

export interface CouponList {
    data: Coupon[];
    meta: Paginated<Coupon>['meta'] & {
        /** Coupons per state for the status chips (the search applied, not the status). */
        status_counts: Record<CouponState | 'all', number>;
    };
}

export interface SaveCouponPayload {
    code: string;
    name: string;
    type: CouponType;
    value: number | null;
    max_discount: number | null;
    min_order_amount: number | null;
    applies_to: 'all' | 'selected';
    category_ids: number[];
    brand_ids: number[];
    tag_ids: number[];
    product_ids: number[];
    excluded_product_ids: number[];
    exclude_discounted: boolean;
    customer_scope: 'all' | 'selected';
    customer_ids: number[];
    usage_limit: number | null;
    per_customer_limit: number | null;
    first_order_only: boolean;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
}

/** Why a line of the sample order gets no coupon discount. */
export type CouponLineReason = 'not_targeted' | 'excluded' | 'discounted';

/** The unsaved coupon applied to a sample cart, by the rules checkout will use. Amounts are Rials. */
export interface CouponSimulation {
    lines: {
        variant_id: number;
        quantity: number;
        /** The current price (sales, sale events and promotions applied). */
        unit_price: number;
        total: number;
        eligible: boolean;
        reason: CouponLineReason | null;
    }[];
    subtotal: number;
    eligible_subtotal: number;
    /** A percent discount before `max_discount`; equal to `discount` when no cap applied. */
    uncapped_discount: number;
    discount: number;
    free_shipping: boolean;
    /** Set when the coupon wouldn't apply to this cart at all. */
    problem: 'min_order' | 'no_eligible_items' | null;
    total: number;
}
