import type { RouteLocationRaw } from 'vue-router';
import type { IconName } from '@/shared/ui/AppIcon.vue';
import { Permission, type PermissionName } from './permissions';

export interface NavItem {
    /** i18n key of the label. */
    label: string;
    icon: IconName;
    /** Omitted for modules that are not built yet; they show as "soon". */
    to?: RouteLocationRaw;
    /** Hidden when the user lacks it. */
    permission?: PermissionName;
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
            { label: 'admin.nav.products', icon: 'package' },
            { label: 'admin.nav.categories', icon: 'tag' },
        ],
    },
    {
        label: 'admin.nav.groupContent',
        items: [
            { label: 'admin.nav.pages', icon: 'file' },
            { label: 'admin.nav.blog', icon: 'pen' },
            { label: 'admin.nav.media', icon: 'image' },
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
            { label: 'admin.nav.settings', icon: 'settings' },
        ],
    },
];
