import type { MediaPicture } from '../media/types';

export type PromotionType = 'percent' | 'fixed';
export type PromotionScope = 'all' | 'selected';
export type PromotionState = 'active' | 'scheduled' | 'expired' | 'disabled';

/** A product, category, brand or tag a promotion applies to (or excludes). */
export interface NamedItem {
    id: number;
    name: string;
}

export interface Promotion {
    id: number;
    name: string;
    type: PromotionType;
    /** A percentage (1–90) or Rials off each unit. */
    value: number;
    /** Percent promotions only: the most Rials taken off one unit. */
    max_discount: number | null;
    scope: PromotionScope;
    state: PromotionState;
    products: NamedItem[];
    categories: NamedItem[];
    brands: NamedItem[];
    tags: NamedItem[];
    excluded_products: NamedItem[];
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
    /** Only on the detail view: how many products it applies to. */
    products_count?: number;
    created_at: string | null;
    updated_at: string | null;
}

export interface PromotionFilters {
    q?: string;
    status?: PromotionState;
    page?: number;
}

export interface SavePromotionPayload {
    name: string;
    type: PromotionType;
    value: number;
    max_discount: number | null;
    scope: PromotionScope;
    product_ids: number[];
    category_ids: number[];
    brand_ids: number[];
    tag_ids: number[];
    excluded_product_ids: number[];
    /** Null: now. */
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
}

// ---- sale events ----

export type SaleEventState = PromotionState;

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
    discount_type: PromotionType;
    /** A percentage (1–90) or Rials off each unit. */
    discount_value: number;
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
        discount_source: 'sale' | 'promotion' | 'sale_event' | null;
        sale_event_id: number | null;
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
    discount_type: PromotionType;
    discount_value: number;
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
