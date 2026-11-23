/** Shapes of the Storefront API responses the storefront reads (frontstore.md). */

export interface Currency {
    display: 'IRT' | 'IRR';
    rials_per_unit: number;
}

export interface Picture {
    id?: number;
    original?: string;
    thumb: string;
    medium: string;
    large?: string;
    alt: string | null;
}

export interface Range {
    min: number;
    max: number;
}

export interface ProductCard {
    id: number;
    name: string;
    slug: string;
    short_description_text: string | null;
    image: Picture | null;
    brand: { name: string; persian_name: string | null; slug: string } | null;
    price: Range;
    regular_price: Range;
    discount_percent: number;
    in_stock: boolean;
    has_variants: boolean;
    variant_id: number | null;
    discount_ends_at: string | null;
}

export interface CategoryNode {
    id: number;
    name: string;
    slug: string;
    image: Picture | null;
    children: CategoryNode[];
}

export interface MenuItem {
    label: string;
    type: 'page' | 'category' | 'url';
    url: string | null;
    page: { slug: string; title: string } | null;
    category: { slug: string; name: string; type: 'product' | 'blog' } | null;
    open_in_new_tab: boolean;
    children: MenuItem[];
}

export interface SaleEventSummary {
    id: number;
    name: string;
    slug: string;
    title: string;
    status: 'upcoming' | 'running' | 'ended';
    starts_at: string;
    ends_at: string;
    banner: Picture | null;
}

export interface Customer {
    id: number;
    name: string | null;
    phone_number: string;
    email: string | null;
}

export interface Cart {
    items_count: number;
    lines_count: number;
}

export interface Paginated<T> {
    data: T[];
    meta: { total: number; currency?: Currency };
}
