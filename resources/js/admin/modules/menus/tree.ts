import type { MenuHiddenReason, MenuItem, MenuItemType, SaveMenuItem } from './types';

/** A menu item while it is edited (new items have no id yet). */
export interface MenuRow {
    /** Stable key for lists and drag and drop. */
    key: string;
    id: number | null;
    type: MenuItemType;
    label: string;
    pageId: number | null;
    categoryId: number | null;
    /** Product or blog category (category items only). */
    categoryType: 'product' | 'blog' | null;
    url: string;
    openInNewTab: boolean;
    /** The page title or category name (shown when the label is empty). */
    targetName: string;
    /** Where it points: the slug or the address. */
    targetDetail: string;
    hiddenReason: MenuHiddenReason | null;
    children: MenuRow[];
}

let counter = 0;

export function newKey(): string {
    counter += 1;

    return `new-${counter}`;
}

export function rowFromItem(item: MenuItem): MenuRow {
    return {
        key: `item-${item.id}`,
        id: item.id,
        type: item.type,
        label: item.label ?? '',
        pageId: item.page?.id ?? null,
        categoryId: item.category?.id ?? null,
        categoryType: item.category?.type ?? null,
        url: item.url ?? '',
        openInNewTab: item.open_in_new_tab,
        targetName: item.page?.title ?? item.category?.name ?? '',
        targetDetail: item.type === 'url' ? (item.url ?? '') : (item.page?.slug ?? item.category?.slug ?? ''),
        hiddenReason: item.hidden_reason,
        children: item.children.map(rowFromItem),
    };
}

/** The label storefronts show: the one typed, else the page title or category name. */
export function displayLabel(row: MenuRow): string {
    return row.label.trim() || row.targetName || row.url;
}

export function toPayload(rows: MenuRow[], withChildren = true): SaveMenuItem[] {
    return rows.map((row) => ({
        id: row.id,
        type: row.type,
        label: row.label.trim() === '' ? null : row.label.trim(),
        page_id: row.type === 'page' ? row.pageId : null,
        category_id: row.type === 'category' ? row.categoryId : null,
        url: row.type === 'url' ? row.url.trim() || null : null,
        open_in_new_tab: row.openInNewTab,
        ...(withChildren ? { children: toPayload(row.children, false) } : {}),
    }));
}

/** The list with the item at `index` moved by `delta` places (the same list when it can't move). */
export function moved<T>(list: T[], index: number, delta: number): T[] {
    const target = index + delta;

    if (index < 0 || index >= list.length || target < 0 || target >= list.length) {
        return list;
    }

    const next = [...list];
    const [item] = next.splice(index, 1);
    next.splice(target, 0, item as T);

    return next;
}

/** Makes the top item at `index` the last sub-item of the one above it (only items without sub-items: two levels). */
export function indented(rows: MenuRow[], index: number): MenuRow[] {
    const row = rows[index];
    const above = rows[index - 1];

    if (!row || !above || row.children.length > 0) {
        return rows;
    }

    return rows
        .filter((_, i) => i !== index)
        .map((item) => (item === above ? { ...above, children: [...above.children, row] } : item));
}

/** Makes a sub-item a top item, right after its parent. */
export function outdented(rows: MenuRow[], parentIndex: number, childIndex: number): MenuRow[] {
    const parent = rows[parentIndex];
    const child = parent?.children[childIndex];

    if (!parent || !child) {
        return rows;
    }

    const next = [...rows];
    next.splice(parentIndex, 1, { ...parent, children: parent.children.filter((_, i) => i !== childIndex) }, child);

    return next;
}
