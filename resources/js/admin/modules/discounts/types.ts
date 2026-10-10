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
