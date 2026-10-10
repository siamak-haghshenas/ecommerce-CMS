import type { RouteLocationRaw } from 'vue-router';
import type { IconName } from '@/shared/ui/AppIcon.vue';
import { Permission, type PermissionName } from './permissions';

export interface NavItem {
    /** i18n key of the label. */
    label: string;
    icon: IconName;
    /** Omitted for modules that are not built yet; they show as "soon". */
    to?: RouteLocationRaw;
    /** Hidden when the user lacks it (a list: any one of them). */
    permission?: PermissionName | PermissionName[];
}

export interface NavGroup {
    label: string;
    items: NavItem[];
}

/** The admin panel's full planned structure. Give an item a `to` when its module ships. */
export const navigation: NavGroup[] = [
    {
        label: 'admin.nav.groupMain',
        items: [{ label: 'admin.nav.dashboard', icon: 'grid', to: { name: 'dashboard' } }],
    },
    {
        label: 'admin.nav.groupSales',
        items: [
            { label: 'admin.nav.orders', icon: 'cart' },
            {
                label: 'admin.nav.customers',
                icon: 'users',
                to: { name: 'customers' },
                permission: Permission.ViewCustomers,
            },
        ],
    },
    {
        label: 'admin.nav.groupCatalog',
        items: [
            {
                label: 'admin.nav.products',
                icon: 'package',
                to: { name: 'products' },
                permission: Permission.ViewProducts,
            },
            {
                label: 'admin.nav.categories',
                icon: 'tag',
                to: { name: 'categories' },
                permission: [Permission.ViewCategories, Permission.ManageBlogCategories],
            },
            {
                label: 'admin.nav.brands',
                icon: 'award',
                to: { name: 'brands' },
                permission: Permission.ViewBrands,
            },
            {
                label: 'admin.nav.attributes',
                icon: 'sliders',
                to: { name: 'attributes' },
                permission: Permission.ViewAttributes,
            },
            {
                label: 'admin.nav.bulkEdit',
                icon: 'pen',
                to: { name: 'products-bulk' },
                permission: Permission.ManageProducts,
            },
            { label: 'admin.nav.tags', icon: 'tag', to: { name: 'tags' }, permission: Permission.ViewProducts },
        ],
    },
    {
        label: 'admin.nav.groupContent',
        items: [
            { label: 'admin.nav.pages', icon: 'file', to: { name: 'pages' }, permission: Permission.ViewPages },
            { label: 'admin.nav.menus', icon: 'menu', to: { name: 'menus' }, permission: Permission.ViewMenus },
            { label: 'admin.nav.blog', icon: 'pen', to: { name: 'posts' }, permission: Permission.ViewPosts },
            { label: 'admin.nav.media', icon: 'image', to: { name: 'media' }, permission: Permission.ViewMedia },
        ],
    },
    {
        label: 'admin.nav.groupSystem',
        items: [
            { label: 'admin.nav.staff', icon: 'shieldCheck', to: { name: 'staff' }, permission: Permission.ViewStaff },
            {
                label: 'admin.nav.activity',
                icon: 'history',
                to: { name: 'activity-log' },
                permission: Permission.ViewActivityLog,
            },
            { label: 'admin.nav.settings', icon: 'settings', to: { name: 'settings' } },
        ],
    },
];
