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
