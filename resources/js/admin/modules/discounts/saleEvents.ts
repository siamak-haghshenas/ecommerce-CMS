// The sale event editor's variant rows: the linked %, amount off and final price fields, the checks
// the server repeats on save, bulk actions, the payload and where server errors go. No Vue, so
// `tests/js/saleEvents.test.mjs` runs it with Node.
import type { SaleEventItem, SaleEventItemPayload, SaleEventVariant } from './types';

/** SaleEvent::MAX_PERCENT */
export const MAX_PERCENT = 90;

/** Which of the three linked fields was typed: it decides what is saved. */
export type DiscountField = 'percent' | 'off' | 'final';

export type RowProblem = 'required' | 'percent_range' | 'not_below_price' | 'dates_order' | 'outside_event';

/** One variant of the event as the editor holds it. Amounts are Rials. */
export interface EventRow {
    variantId: number;
    productId: number;
    productName: string;
    productType: 'simple' | 'variable';
    productImage: string | null;
    label: string;
    sku: string;
    price: number;
    currentPrice: number;
    /** What gives the variant's price now (null: the regular price). */
    discountSource: 'sale' | 'promotion' | 'sale_event' | null;
    /** The event giving the price now, when it comes from an event. */
    saleEventId: number | null;
    isEnabled: boolean;
    isDeleted: boolean;
    field: DiscountField;
    /** The typed field's value: a percentage, Rials off or the final price in Rials. Null: not filled in. */
    value: number | null;
    /** `datetime-local` values; empty: the event's dates. */
    startsAt: string;
    endsAt: string;
    overriddenBy: SaleEventItem['overridden_by'];
}

export function rowFromItem(item: SaleEventItem, toLocal: (iso: string | null) => string): EventRow {
    return {
        variantId: item.variant.id,
        productId: item.product.id,
        productName: item.product.name,
        productType: item.product.type,
        productImage: item.product.image,
        label: item.variant.label,
        sku: item.variant.sku,
        price: item.variant.price,
        currentPrice: item.variant.current_price,
        discountSource: item.variant.discount_source,
        saleEventId: item.variant.sale_event_id,
        isEnabled: item.variant.is_enabled && !item.variant.is_deleted,
        isDeleted: item.variant.is_deleted || item.product.is_deleted,
        field: item.discount_type === 'percent' ? 'percent' : 'off',
        value: item.discount_value,
        startsAt: toLocal(item.starts_at),
        endsAt: toLocal(item.ends_at),
        overriddenBy: item.overridden_by,
    };
}

/**
 * Variants of the "Add products" dialog not yet in the event, as new rows with an optional starting
 * discount. Their price source is left out: it says nothing about this event until it is saved.
 */
export function rowsFromVariants(
    variants: SaleEventVariant[],
    existing: Set<number>,
    start: { field: 'percent' | 'off'; value: number | null } = { field: 'percent', value: null },
): EventRow[] {
    return variants
        .filter(({ variant }) => !existing.has(variant.id))
        .map((item) => ({
            ...rowFromItem(
                {
                    ...item,
                    id: 0,
                    discount_type: start.field === 'percent' ? 'percent' : 'fixed',
                    discount_value: 0,
                    starts_at: null,
                    ends_at: null,
                    overridden_by: [],
                },
                () => '',
            ),
            discountSource: null,
            saleEventId: null,
            value: start.value,
        }));
}

/** Rials off before rounding, or null while the row has no usable value. */
function rawOff(row: EventRow): number | null {
    if (row.value === null) {
        return null;
    }

    switch (row.field) {
        case 'percent':
            return Math.floor((row.price * row.value) / 100);
        case 'off':
            return row.value;
        case 'final':
            return row.price - row.value;
    }
}

/**
 * What the customer gets off, like `Pricing::eventDiscount()`: rounded down to whole display units
 * (10 Rials when the store shows Toman), never more than the price.
 */
export function discountOf(row: EventRow, rialsPerUnit: number): number | null {
    const off = rawOff(row);

    return off === null ? null : Math.max(0, Math.min(row.price, Math.floor(off / rialsPerUnit) * rialsPerUnit));
}

/** The three linked fields as shown: the typed one as typed, the other two computed from it. */
export function linkedValues(
    row: EventRow,
    rialsPerUnit: number,
): { percent: number | null; off: number | null; final: number | null } {
    const off = discountOf(row, rialsPerUnit);

    if (off === null) {
        return { percent: null, off: null, final: null };
    }

    return {
        percent: row.field === 'percent' ? row.value : row.price > 0 ? Math.round((off / row.price) * 1000) / 10 : 0,
        off: row.field === 'off' ? row.value : off,
        final: row.field === 'final' ? row.value : row.price - off,
    };
}

/** The row after typing into one of the linked fields. */
export function typed(row: EventRow, field: DiscountField, value: number | null): EventRow {
    return { ...row, field, value };
}

/** What is saved: a percentage if the % was typed, otherwise a fixed amount off. */
export function toItemPayload(row: EventRow, fromLocal: (value: string) => string | null): SaleEventItemPayload {
    return {
        variant_id: row.variantId,
        discount_type: row.field === 'percent' ? 'percent' : 'fixed',
        discount_value: (row.field === 'percent' ? row.value : rawOff(row)) ?? 0,
        starts_at: fromLocal(row.startsAt),
        ends_at: fromLocal(row.endsAt),
    };
}

/** The first problem the server would report for the row (`SaveSaleEventRequest`), or null. */
export function rowProblem(row: EventRow, event: { startsAt: string; endsAt: string }): RowProblem | null {
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

    if (row.startsAt !== '' && row.endsAt !== '' && row.endsAt <= row.startsAt) {
        return 'dates_order';
    }

    const outside =
        (row.startsAt !== '' &&
            ((event.startsAt !== '' && row.startsAt < event.startsAt) ||
                (event.endsAt !== '' && row.startsAt >= event.endsAt))) ||
        (row.endsAt !== '' &&
            ((event.endsAt !== '' && row.endsAt > event.endsAt) ||
                (event.startsAt !== '' && row.endsAt <= event.startsAt)));

    return outside ? 'outside_event' : null;
}

/**
 * The same discount on several rows. With an amount off or a final price, each row keeps its own
 * price as the base; rows where that isn't possible (amount ≥ price) are left as they were and
 * returned in `skipped`.
 */
export function applyDiscount(
    rows: EventRow[],
    ids: Set<number>,
    field: DiscountField,
    value: number,
): { rows: EventRow[]; skipped: number[] } {
    const skipped: number[] = [];

    const next = rows.map((row) => {
        if (!ids.has(row.variantId)) {
            return row;
        }

        const candidate = typed(row, field, value);
        const off = rawOff(candidate) ?? 0;

        if (field !== 'percent' && (off < 1 || off >= row.price)) {
            skipped.push(row.variantId);

            return row;
        }

        return candidate;
    });

    return { rows: next, skipped };
}

/** The same window on several rows (empty strings: back to the event's dates). */
export function applyDates(rows: EventRow[], ids: Set<number>, startsAt: string, endsAt: string): EventRow[] {
    return rows.map((row) => (ids.has(row.variantId) ? { ...row, startsAt, endsAt } : row));
}

/** Rows grouped by product, products in the order they were first added. */
export function groupByProduct(rows: EventRow[]): { productId: number; rows: EventRow[] }[] {
    const groups = new Map<number, EventRow[]>();

    for (const row of rows) {
        groups.set(row.productId, [...(groups.get(row.productId) ?? []), row]);
    }

    return [...groups.entries()].map(([productId, grouped]) => ({ productId, rows: grouped }));
}

/**
 * Server errors (`items.3.discount_value`) by variant id. The payload is built from `rows` in
 * order, so item 3 is `rows[3]`.
 */
export function mapItemErrors(rows: EventRow[], errors: Record<string, string[]>): Map<number, string> {
    const byVariant = new Map<number, string>();

    for (const [key, messages] of Object.entries(errors)) {
        const match = /^items\.(\d+)\./.exec(key);
        const row = match ? rows[Number(match[1])] : undefined;

        if (row && messages[0] && !byVariant.has(row.variantId)) {
            byVariant.set(row.variantId, messages[0]);
        }
    }

    return byVariant;
}
