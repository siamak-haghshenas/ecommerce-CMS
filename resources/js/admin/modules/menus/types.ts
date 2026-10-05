export type MenuLocation = 'header' | 'footer';
export type MenuItemType = 'page' | 'category' | 'url';
/** Why storefronts don't show an item. */
export type MenuHiddenReason = 'page_draft' | 'page_scheduled' | 'page_trashed' | 'category_hidden';

export interface MenuItem {
    id: number;
    type: MenuItemType;
    /** As typed; null for page and category items that use the title / name. */
    label: string | null;
    display_label: string;
    page: { id: number; title: string; slug: string } | null;
    category: { id: number; name: string; slug: string; type: 'product' | 'blog' } | null;
    url: string | null;
    open_in_new_tab: boolean;
    is_visible: boolean;
    hidden_reason: MenuHiddenReason | null;
    children: MenuItem[];
}

export interface SaveMenuItem {
    id: number | null;
    type: MenuItemType;
    label: string | null;
    page_id: number | null;
    category_id: number | null;
    url: string | null;
    open_in_new_tab: boolean;
    children?: SaveMenuItem[];
}

/** What menu items can link to. */
export interface MenuTargets {
    pages: { id: number; title: string; slug: string; state: 'draft' | 'scheduled' | 'published' }[];
    /** The category tree in order, flattened. */
    categories: {
        id: number;
        type: 'product' | 'blog';
        name: string;
        slug: string;
        depth: number;
        is_visible: boolean;
    }[];
}
