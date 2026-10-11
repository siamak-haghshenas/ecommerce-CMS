// The promotions page's rows (one SKU discount each): the linked percentage and off price, the
// checks the server repeats on save (overlapping dates included), what changed, bulk edits, the
// payload, pages and where server errors go. No Vue, so `tests/js/promotions.test.mjs` runs it with
// Node.
import { type DiscountField, linkedValues, MAX_PERCENT, rawOff } from './saleEvents.ts';
import type { Promotion, PromotionPayload, PromotionPickerProduct, PromotionState } from './types';

/** Promotion::MAX_PER_ORDER and Promotion::MAX_QUANTITY */
export const MAX_PER_ORDER = 10_000;
export const MAX_QUANTITY = 1_000_000;

export type PromotionProblem =
    | 'required'
    | 'percent_range'
    | 'not_below_price'
    | 'limit_range'
    | 'quantity_range'
    | 'dates_required'
    | 'dates_order'
    | 'overlap';

/** One row as the page holds it. Amounts are Rials; dates are `datetime-local` values. */
export interface PromotionRow {
    /** Stable while editing: `p{id}` for a saved row, `n{n}` for a new one. */
    key: string;
    /** Null: not saved yet. */
    id: number | null;
    variantId: number;
    productId: number;
    productName: string;
    productType: 'simple' | 'variable';
    productImage: string | null;
    label: string;
    sku: string;
    price: number;
    currentPrice: number;
    /** What gives the variant's price now, and the promotion row giving it. */
    discountSource: 'sale' | 'sale_event' | 'promotion' | null;
    promotionId: number | null;
    isDeleted: boolean;
    /** The linked field that was typed ("off" only comes from the bulk dialog). */
    field: DiscountField;
    value: number | null;
    maxPerOrder: number | null;
    quantityLimit: number | null;
    soldCount: number;
    startsAt: string;
    endsAt: string;
    /** What was saved (see `snapshot()`); null for a new row. */
    original: string | null;
}

export function rowFromPromotion(promotion: Promotion, toLocal: (iso: string | null) => string): PromotionRow {
    const row: PromotionRow = {
        key: `p${promotion.id}`,
        id: promotion.id,
        variantId: promotion.variant.id,
        productId: promotion.product.id,
        productName: promotion.product.name,
        productType: promotion.product.type,
        productImage: promotion.product.image,
        label: promotion.variant.label,
        sku: promotion.variant.sku,
        price: promotion.variant.price,
        currentPrice: promotion.variant.current_price,
        discountSource: promotion.variant.discount_source,
        promotionId: promotion.variant.promotion_id,
        isDeleted: promotion.variant.is_deleted || promotion.product.is_deleted,
        field: promotion.discount_type === 'percent' ? 'percent' : 'off',
        value: promotion.discount_value,
        maxPerOrder: promotion.max_per_order,
        quantityLimit: promotion.quantity_limit,
        soldCount: promotion.sold_count,
        startsAt: toLocal(promotion.starts_at),
        endsAt: toLocal(promotion.ends_at),
        original: null,
    };

    return { ...row, original: snapshot(row) };
}

/** Starting values of rows added from "Add product". */
export interface NewRowDefaults {
    percent: number | null;
    maxPerOrder: number | null;
    quantityLimit: number | null;
    startsAt: string;
    endsAt: string;
}

/** Variants chosen in "Add product" as new rows; `next` numbers their keys. */
export function newRows(
    chosen: { product: PromotionPickerProduct; variant: PromotionPickerProduct['variants'][number] }[],
    defaults: NewRowDefaults,
    next: number,
): PromotionRow[] {
    return chosen.map(({ product, variant }, index) => ({
        key: `n${next + index}`,
        id: null,
        variantId: variant.id,
        productId: product.id,
        productName: product.name,
        productType: product.type,
        productImage: product.image,
        label: variant.label,
        sku: variant.sku,
        price: variant.price,
        currentPrice: variant.current_price,
        discountSource: null,
        promotionId: null,
        isDeleted: false,
        field: 'percent',
        value: defaults.percent,
        maxPerOrder: defaults.maxPerOrder,
        quantityLimit: defaults.quantityLimit,
        soldCount: 0,
        startsAt: defaults.startsAt,
        endsAt: defaults.endsAt,
        original: null,
    }));
}

/** The discount as saved: a percentage if the % was typed, otherwise a fixed amount off. */
function savedDiscount(row: PromotionRow): { type: 'percent' | 'fixed'; value: number | null } {
    return row.field === 'percent' ? { type: 'percent', value: row.value } : { type: 'fixed', value: rawOff(row) };
}

/** The saved fields as text, to tell whether a row changed (typing the same discount another way is no change). */
export function snapshot(row: PromotionRow): string {
    return JSON.stringify([savedDiscount(row), row.maxPerOrder, row.quantityLimit, row.startsAt, row.endsAt]);
}

export function isChanged(row: PromotionRow): boolean {
    return row.original === null || snapshot(row) !== row.original;
}

/** The percentage and off price as shown: the typed one as typed, the other computed from it. */
export function rowValues(row: PromotionRow, rialsPerUnit: number): { percent: number | null; final: number | null } {
    const { percent, final } = linkedValues(row, rialsPerUnit);

    return { percent, final };
}

/** "scheduled", "active", "sold_out" or "ended" at `now` (a `datetime-local` value), like `Promotion::state()`. */
export function rowState(row: PromotionRow, now: string): PromotionState {
    if (row.endsAt !== '' && row.endsAt <= now) {
        return 'ended';
    }

    if (row.quantityLimit !== null && row.soldCount >= row.quantityLimit) {
        return 'sold_out';
    }

    return row.startsAt !== '' && row.startsAt > now ? 'scheduled' : 'active';
}

const outOfRange = (value: number | null, max: number): boolean => value !== null && (value < 1 || value > max);

/** The first problem the server would report for the row (`SavePromotionsRequest`), or null. */
export function rowProblem(row: PromotionRow, rows: PromotionRow[]): PromotionProblem | null {
    const off = rawOff(row);

    if (row.value === null || off === null) {
        return 'required';
    }

    if (row.field === 'percent') {
        if (row.value < 1 || row.value > MAX_PERCENT) {
            return 'percent_range';
        }
    } else if (off < 1) {
        return 'required';
    } else if (off >= row.price) {
        return 'not_below_price';
    }

    if (outOfRange(row.maxPerOrder, MAX_PER_ORDER)) {
        return 'limit_range';
    }

    if (outOfRange(row.quantityLimit, MAX_QUANTITY)) {
        return 'quantity_range';
    }

    if (row.startsAt === '' || row.endsAt === '') {
        return 'dates_required';
    }

    if (row.endsAt <= row.startsAt) {
        return 'dates_order';
    }

    const overlaps = rows.some(
        (other) =>
            other.key !== row.key &&
            other.variantId === row.variantId &&
            other.startsAt !== '' &&
            other.endsAt !== '' &&
            row.startsAt < other.endsAt &&
            other.startsAt < row.endsAt,
    );

    return overlaps ? 'overlap' : null;
}

/** What one bulk edit changes; a missing key leaves that field as it is. */
export interface BulkChange {
    discount?: { field: DiscountField; value: number };
    maxPerOrder?: number | null;
    quantityLimit?: number | null;
    startsAt?: string;
    endsAt?: string;
}

/**
 * One bulk edit on several rows. An amount off or off price is taken from each row's own price;
 * rows where that isn't possible (nothing off, or not below the price) keep their discount and are
 * returned in `skipped` (their other changes still apply).
 */
export function applyBulk(
    rows: PromotionRow[],
    keys: Set<string>,
    change: BulkChange,
): { rows: PromotionRow[]; skipped: string[] } {
    const skipped: string[] = [];

    const next = rows.map((row) => {
        if (!keys.has(row.key)) {
            return row;
        }

        let changed: PromotionRow = {
            ...row,
            ...('maxPerOrder' in change ? { maxPerOrder: change.maxPerOrder ?? null } : {}),
            ...('quantityLimit' in change ? { quantityLimit: change.quantityLimit ?? null } : {}),
            ...(change.startsAt !== undefined ? { startsAt: change.startsAt } : {}),
            ...(change.endsAt !== undefined ? { endsAt: change.endsAt } : {}),
        };

        if (change.discount) {
            const candidate = { ...changed, field: change.discount.field, value: change.discount.value };
            const off = rawOff(candidate) ?? 0;

            if (change.discount.field !== 'percent' && (off < 1 || off >= row.price)) {
                skipped.push(row.key);
            } else {
                changed = candidate;
            }
        }

        return changed;
    });

    return { rows: next, skipped };
}

/** A row as the save request takes it. */
export function toPayload(row: PromotionRow, fromLocal: (value: string) => string | null): PromotionPayload {
    const discount = savedDiscount(row);

    return {
        id: row.id,
        variant_id: row.variantId,
        discount_type: discount.type,
        discount_value: discount.value ?? 0,
        max_per_order: row.maxPerOrder,
        quantity_limit: row.quantityLimit,
        starts_at: fromLocal(row.startsAt),
        ends_at: fromLocal(row.endsAt),
    };
}

/**
 * Server errors (`promotions.3.discount_value`) by row key. `sent` is the list the payload was
 * built from, in order.
 */
export function mapErrors(sent: PromotionRow[], errors: Record<string, string[]>): Map<string, string> {
    const byKey = new Map<string, string>();

    for (const [field, messages] of Object.entries(errors)) {
        const match = /^promotions\.(\d+)\./.exec(field);
        const row = match ? sent[Number(match[1])] : undefined;

        if (row && messages[0] && !byKey.has(row.key)) {
            byKey.set(row.key, messages[0]);
        }
    }

    return byKey;
}

/** One page of a list, with the pagination meta `AppPagination` reads. */
export function pageOf<T>(
    items: T[],
    page: number,
    perPage: number,
): {
    items: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
} {
    const lastPage = Math.max(1, Math.ceil(items.length / perPage));
    const current = Math.min(Math.max(1, page), lastPage);
    const start = (current - 1) * perPage;
    const shown = items.slice(start, start + perPage);

    return {
        items: shown,
        meta: {
            current_page: current,
            last_page: lastPage,
            per_page: perPage,
            total: items.length,
            from: shown.length === 0 ? null : start + 1,
            to: shown.length === 0 ? null : start + shown.length,
        },
    };
}
