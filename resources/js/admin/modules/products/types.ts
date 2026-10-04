import type { OptionDisplayType } from '../attributes/types';

export type ProductType = 'simple' | 'variable';
export type ProductStatus = 'draft' | 'published' | 'archived';

export interface ProductImage {
    id: number;
    original: string;
    thumb: string;
    medium: string;
    large: string;
}

/** A row of the product list. Amounts are Rials. */
export interface ProductListItem {
    id: number;
    type: ProductType;
    name: string;
    slug: string;
    status: ProductStatus;
    published_at: string | null;
    image: string | null;
    brand: { id: number; name: string } | null;
    primary_category: { id: number; name: string } | null;
    price: { min: number; max: number } | null;
    on_sale: boolean;
    /** Null when stock isn't tracked. */
    stock: { total: number; low: boolean; out: boolean } | null;
    variants_count: number;
    seo_score: number | null;
    updated_at: string | null;
    deleted_at: string | null;
}

export interface ProductOption {
    id: number;
    name: string;
    slug: string;
    display_type: OptionDisplayType;
    values: { id: number; value: string; slug: string; color_hex: string | null }[];
}

export interface ProductVariant {
    id: number;
    sku: string;
    price: number;
    sale_price: number | null;
    sale_starts_at: string | null;
    sale_ends_at: string | null;
    current_price: number;
    discount_percent: number;
    stock_quantity: number;
    is_enabled: boolean;
    image_media_id: number | null;
    option_value_ids: number[];
}

export interface ProductSpecification {
    attribute_id: number;
    value_ids: number[];
    text: string | null;
    number: number | string | null;
    boolean: boolean | null;
}

/** Everything the product editor needs. */
export interface Product {
    id: number;
    type: ProductType;
    name: string;
    slug: string;
    short_description: string | null;
    description: string | null;
    status: ProductStatus;
    published_at: string | null;
    brand: { id: number; name: string } | null;
    categories: { id: number; name: string; is_primary: boolean }[];
    tags: { id: number; name: string; slug: string }[];
    track_stock: boolean;
    allow_backorders: boolean;
    low_stock_threshold: number | null;
    low_stock_threshold_effective: number;
    weight_grams: number | null;
    length_mm: number | null;
    width_mm: number | null;
    height_mm: number | null;
    options: ProductOption[];
    variants: ProductVariant[];
    specifications: ProductSpecification[];
    images: ProductImage[];
    meta_title: string | null;
    meta_description: string | null;
    focus_keyphrase: string | null;
    seo_score: number | null;
    readability_score: number | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
}

/** A variant as sent by the editor. Existing stock is sent with what the admin saw (`stock_quantity_was`). */
export interface VariantPayload {
    id: number | null;
    sku: string;
    price: number;
    sale_price: number | null;
    sale_starts_at: string | null;
    sale_ends_at: string | null;
    is_enabled: boolean;
    image_media_id: number | null;
    option_value_ids: number[];
    stock_quantity: number | null;
    stock_quantity_was: number | null;
}

export interface SaveProductPayload {
    type: ProductType;
    name: string;
    slug: string;
    short_description: string | null;
    description: string | null;
    status: ProductStatus;
    published_at: string | null;
    brand_id: number | null;
    category_ids: number[];
    primary_category_id: number | null;
    tags: string[];
    track_stock: boolean;
    allow_backorders: boolean;
    low_stock_threshold: number | null;
    weight_grams: number | null;
    length_mm: number | null;
    width_mm: number | null;
    height_mm: number | null;
    meta_title: string | null;
    meta_description: string | null;
    focus_keyphrase: string | null;
    option_ids: number[];
    variants: VariantPayload[];
    specifications: {
        attribute_id: number;
        value_ids?: number[];
        text?: string | null;
        number?: number | null;
        boolean?: boolean | null;
    }[];
}

export interface ProductFilters {
    q?: string;
    status?: ProductStatus;
    category_id?: number;
    brand_id?: number;
    tag_id?: number;
    stock?: 'low' | 'out';
    needs_seo?: 1;
    trashed?: 1;
    sort?: 'newest' | 'name' | 'updated';
    page?: number;
    per_page?: number;
    /** The bulk edit page's quick filters. */
    quick?: 'out' | 'low' | 'sale' | 'inactive';
}

export interface StockMovement {
    id: number;
    variant: { id: number; sku: string | null };
    user: { id: number; name: string } | null;
    reason: 'initial' | 'adjustment' | 'bulk_edit' | 'order' | 'order_cancelled' | 'return';
    quantity_change: number;
    quantity_before: number;
    quantity_after: number;
    note: string | null;
    created_at: string | null;
}

export interface Tag {
    id: number;
    name: string;
    slug: string;
    products_count?: number;
}
