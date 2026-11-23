import type { MenuItem } from './types';

/** Storefront addresses, in one place so pages and menus agree. */
export const paths = {
    home: '/',
    categories: '/categories',
    category: (slug: string) => `/category/${encodeURIComponent(slug)}`,
    product: (slug: string) => `/product/${encodeURIComponent(slug)}`,
    deals: '/deals',
    saleEvent: (slug: string) => `/sale/${encodeURIComponent(slug)}`,
    search: (q: string) => `/search?q=${encodeURIComponent(q)}`,
    page: (slug: string) => `/page/${encodeURIComponent(slug)}`,
    blog: '/blog',
    blogCategory: (slug: string) => `/blog?category=${encodeURIComponent(slug)}`,
    cart: '/cart',
    login: '/login',
    account: '/account',
};

export interface MenuLink {
    label: string;
    /** An address inside the storefront (RouterLink), or null for an external one. */
    to: string | null;
    href: string | null;
    newTab: boolean;
}

/** Where a CMS menu item leads. */
export function menuLink(item: MenuItem): MenuLink {
    let to: string | null = null;
    let href: string | null = null;

    if (item.page !== null) {
        to = paths.page(item.page.slug);
    } else if (item.category !== null) {
        to =
            item.category.type === 'blog' ? paths.blogCategory(item.category.slug) : paths.category(item.category.slug);
    } else if (item.url !== null) {
        if (item.url.startsWith('/')) {
            to = item.url;
        } else {
            href = item.url;
        }
    }

    return { label: item.label, to, href, newTab: item.open_in_new_tab };
}
