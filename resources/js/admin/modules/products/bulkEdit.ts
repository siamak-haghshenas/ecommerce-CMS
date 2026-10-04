import type { ProductStatus, ProductType } from './types';

/**
 * The bulk edit page: the current page's products with their variants, and the changes made to
 * them before Save. Only differences from the loaded values are kept, so typing a value back
 * removes the change. Amounts are Rials. No imports besides types (Node's test runner loads it).
 */

export interface BulkVariant {
    id: number;
    sku: string;
    /** Option values ("M · Red"); empty for a simple product. */
    label: string;
    /** The variant's own image (thumb), if it has one. */
    image: string | null;
    price: number;
    sale_price: number | null;
    sale_ends_at: string | null;
    stock_quantity: number;
    is_enabled: boolean;
}

export interface BulkProduct {
    id: number;
    type: ProductType;
    name: string;
    status: ProductStatus;
    image: string | null;
    track_stock: boolean;
    /** At or below this, stock counts as low. */
    low_stock_threshold: number;
    variants: BulkVariant[];
}

export type EditableField = 'price' | 'sale_price' | 'stock_quantity' | 'is_enabled';
export type VariantValues = Pick<BulkVariant, EditableField>;
/** A variant's changed values. Numbers are null while the field is empty. */
export type VariantEdits = Partial<{ [F in EditableField]: F extends 'is_enabled' ? boolean : number | null }>;
/** Changes on the page, by variant id. */
export type PageEdits = Record<number, VariantEdits>;

export function valueOf<F extends EditableField>(edits: PageEdits, variant: BulkVariant, field: F): VariantEdits[F] {
    const changed = edits[variant.id];

    return changed && field in changed ? changed[field] : variant[field];
}

export function isEdited(edits: PageEdits, variantId: number, field?: EditableField): boolean {
    const changed = edits[variantId];

    return !!changed && (field === undefined || field in changed);
}

export function setValue<F extends EditableField>(
    edits: PageEdits,
    variant: BulkVariant,
    field: F,
    value: VariantEdits[F],
): PageEdits {
    const changed: VariantEdits = { ...(edits[variant.id] ?? {}) };

    if (value === variant[field]) {
        delete changed[field];
    } else {
        changed[field] = value;
    }

    const next = { ...edits };

    if (Object.keys(changed).length === 0) {
        delete next[variant.id];
    } else {
        next[variant.id] = changed;
    }

    return next;
}

export function countChanges(edits: PageEdits): number {
    return Object.values(edits).reduce((total, changed) => total + Object.keys(changed).length, 0);
}

export type FieldProblem = 'required' | 'saleNotLower';

/** Problems the page can show while typing (the server checks again on save). */
export function problems(edits: PageEdits, variant: BulkVariant): Partial<Record<EditableField, FieldProblem>> {
    const found: Partial<Record<EditableField, FieldProblem>> = {};
    const price = valueOf(edits, variant, 'price');
    const sale = valueOf(edits, variant, 'sale_price');

    if (price === null || price === undefined) {
        found.price = 'required';
    }

    if (valueOf(edits, variant, 'stock_quantity') === null) {
        found.stock_quantity = 'required';
    }

    if (typeof sale === 'number' && typeof price === 'number' && sale >= price) {
        found.sale_price = 'saleNotLower';
    }

    return found;
}

/** Whole-percent discount of a valid sale, else null. */
export function discountPercent(price: number | null | undefined, sale: number | null | undefined): number | null {
    if (typeof price !== 'number' || typeof sale !== 'number' || price <= 0 || sale >= price) {
        return null;
    }

    return Math.round(((price - sale) / price) * 100);
}

export type StockLevel = 'untracked' | 'out' | 'low' | 'ok';

export function stockLevel(product: BulkProduct, quantity: number | null): StockLevel {
    if (!product.track_stock) {
        return 'untracked';
    }

    if (quantity === null || quantity <= 0) {
        return 'out';
    }

    return quantity <= product.low_stock_threshold ? 'low' : 'ok';
}

export interface BulkVariantPayload {
    id: number;
    price?: number;
    sale_price?: number | null;
    is_enabled?: boolean;
    stock_quantity?: number;
    /** The quantity the page loaded, so the server can refuse stock that changed meanwhile. */
    stock_quantity_was?: number;
}

/** Every changed variant on the page, only its changed fields. */
export function toPayload(edits: PageEdits, products: BulkProduct[]): BulkVariantPayload[] {
    return products.flatMap((product) =>
        product.variants.flatMap((variant): BulkVariantPayload[] => {
            const changed = edits[variant.id];

            if (!changed) {
                return [];
            }

            const row = { id: variant.id, ...changed } as BulkVariantPayload;

            if ('stock_quantity' in changed) {
                row.stock_quantity_was = variant.stock_quantity;
            }

            return [row];
        }),
    );
}

export type RowErrors = Record<number, Partial<Record<EditableField, string>>>;

/** Places the server's errors (`variants.3.sale_price`) on the variants they belong to. */
export function mapErrors(payload: BulkVariantPayload[], errors: Record<string, string[]>): RowErrors {
    const mapped: RowErrors = {};

    for (const [key, messages] of Object.entries(errors)) {
        const match = /^variants\.(\d+)\.(\w+)$/.exec(key);
        const variant = match ? payload[Number(match[1])] : undefined;
        // A row-level error (e.g. the product was deleted) is shown under the price.
        const field =
            match?.[2] === 'id' ? 'price' : match?.[2] === 'stock_quantity_was' ? 'stock_quantity' : match?.[2];

        if (variant && field) {
            mapped[variant.id] = { ...(mapped[variant.id] ?? {}), [field as EditableField]: messages[0] ?? '' };
        }
    }

    return mapped;
}
