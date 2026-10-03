import type { ProductOption, ProductVariant, VariantPayload } from './types';

/** A variant row being edited. Amounts are Rials; dates are `datetime-local` strings. */
export interface VariantRow {
    key: string;
    id: number | null;
    optionValueIds: number[];
    sku: string;
    price: number | null;
    salePrice: number | null;
    saleStartsAt: string;
    saleEndsAt: string;
    isEnabled: boolean;
    imageMediaId: number | null;
    stock: number | null;
    /** The stock the admin was shown (existing variants), sent back to detect concurrent changes. */
    stockWas: number | null;
    /** The SKU was typed by the admin, so it is no longer suggested. */
    skuEdited: boolean;
}

let nextKey = 0;

/** The identity of a combination, whatever the order of its values. */
export function comboKey(valueIds: number[]): string {
    return [...valueIds].sort((a, b) => a - b).join('-');
}

/** ISO date → value of an `<input type="datetime-local">` in local time. */
export function toLocalInput(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    const pad = (value: number): string => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function fromLocalInput(value: string): string | null {
    return value === '' ? null : new Date(value).toISOString();
}

export function rowFromVariant(variant: ProductVariant): VariantRow {
    return {
        key: `variant-${variant.id}`,
        id: variant.id,
        optionValueIds: [...variant.option_value_ids],
        sku: variant.sku,
        price: variant.price,
        salePrice: variant.sale_price,
        saleStartsAt: toLocalInput(variant.sale_starts_at),
        saleEndsAt: toLocalInput(variant.sale_ends_at),
        isEnabled: variant.is_enabled,
        imageMediaId: variant.image_media_id,
        stock: variant.stock_quantity,
        stockWas: variant.stock_quantity,
        skuEdited: true,
    };
}

export function newRow(optionValueIds: number[] = [], template?: VariantRow): VariantRow {
    return {
        key: `new-${nextKey++}`,
        id: null,
        optionValueIds,
        sku: '',
        price: template?.price ?? null,
        salePrice: null,
        saleStartsAt: '',
        saleEndsAt: '',
        isEnabled: true,
        imageMediaId: null,
        stock: template && template.id === null ? template.stock : null,
        stockWas: null,
        skuEdited: false,
    };
}

/** Every combination of the chosen values, one value per option, in option order. */
export function combinations(valueIdsPerOption: number[][]): number[][] {
    return valueIdsPerOption.reduce<number[][]>(
        (combos, values) => combos.flatMap((combo) => values.map((value) => [...combo, value])),
        [[]],
    );
}

/**
 * The rows for a new set of combinations: existing rows (matched by combination) are kept with
 * their id, price and stock; new combinations get new rows priced like the first row.
 */
export function regenerate(rows: VariantRow[], combos: number[][]): VariantRow[] {
    const byKey = new Map(rows.map((row) => [comboKey(row.optionValueIds), row]));
    const template = rows[0];

    return combos.map((combo) => byKey.get(comboKey(combo)) ?? newRow(combo, template));
}

/** "BASE-RED-XL" from the product slug and the value slugs. */
export function suggestSku(base: string, valueSlugs: string[]): string {
    return [base, ...valueSlugs]
        .map((part) => part.toUpperCase().replace(/[^A-Z0-9._/-]/g, ''))
        .filter((part) => part !== '')
        .join('-')
        .slice(0, 64);
}

/** Display names of a row's option values, in option order ("Red / XL"). */
export function rowLabel(row: VariantRow, options: ProductOption[]): string {
    return options
        .map((option) => option.values.find((value) => row.optionValueIds.includes(value.id))?.value ?? '?')
        .join(' / ');
}

export function toPayload(row: VariantRow, trackStock: boolean): VariantPayload {
    // Stock is only sent when tracked; for existing variants together with what the admin saw.
    const sendStock = trackStock && row.stock !== null && (row.id === null || row.stock !== row.stockWas);

    return {
        id: row.id,
        sku: row.sku.trim(),
        price: row.price ?? 0,
        sale_price: row.salePrice,
        sale_starts_at: row.salePrice === null ? null : fromLocalInput(row.saleStartsAt),
        sale_ends_at: row.salePrice === null ? null : fromLocalInput(row.saleEndsAt),
        is_enabled: row.isEnabled,
        image_media_id: row.imageMediaId,
        option_value_ids: row.optionValueIds,
        stock_quantity: sendStock ? row.stock : null,
        stock_quantity_was: sendStock && row.id !== null ? row.stockWas : null,
    };
}
