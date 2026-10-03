import type { ValueInput } from './types';

/** A value row being edited; `key` keeps rows stable while dragging (new rows have no id yet). */
export interface ValueRow {
    key: string;
    id: number | null;
    value: string;
    slug: string;
    colorHex: string;
    /** Stop suggesting the slug from the value once the admin edits it. */
    slugEdited: boolean;
}

let nextKey = 0;

export function valueRow(value?: { id: number; value: string; slug: string; color_hex?: string | null }): ValueRow {
    return {
        key: `row-${nextKey++}`,
        id: value?.id ?? null,
        value: value?.value ?? '',
        slug: value?.slug ?? '',
        colorHex: value?.color_hex ?? '#888888',
        slugEdited: value !== undefined,
    };
}

export function toValueInputs(rows: ValueRow[], withColor: boolean): ValueInput[] {
    return rows.map((row) => ({
        id: row.id,
        value: row.value.trim(),
        slug: row.slug.trim().toLowerCase(),
        ...(withColor ? { color_hex: row.colorHex } : {}),
    }));
}
