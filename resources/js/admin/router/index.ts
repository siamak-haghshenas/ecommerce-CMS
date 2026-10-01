import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import AdminAuthLayout from '../layouts/AdminAuthLayout.vue';
import AdminLayout from '../layouts/AdminLayout.vue';
import { useAdminAuthStore } from '../modules/auth/store';
import { Permission, type PermissionName } from '../permissions';

const routes: RouteRecordRaw[] = [
    {
        path: '/login',
        component: AdminAuthLayout,
        children: [
            {
                path: '',
                name: 'login',
                component: () => import('../modules/auth/pages/LoginPage.vue'),
                meta: { title: 'pageTitles.adminLogin', guestOnly: true },
            },
        ],
    },
    {
        path: '/',
        component: AdminLayout,
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                name: 'dashboard',
                component: () => import('../modules/dashboard/pages/DashboardPage.vue'),
                meta: { title: 'pageTitles.dashboard' },
            },
            {
                path: 'customers',
                name: 'customers',
                component: () => import('../modules/customers/pages/CustomersPage.vue'),
                meta: { title: 'pageTitles.customers', permission: Permission.ViewCustomers },
            },
            {
                path: 'customers/:id(\\d+)',
                name: 'customer',
                component: () => import('../modules/customers/pages/CustomerDetailPage.vue'),
                props: (route) => ({ id: Number(route.params.id) }),
                meta: { title: 'pageTitles.customer', permission: Permission.ViewCustomers },
            },
            {
                path: 'staff',
                name: 'staff',
                component: () => import('../modules/staff/pages/StaffPage.vue'),
                meta: { title: 'pageTitles.staff', permission: Permission.ViewStaff },
            },
            {
                path: 'activity-log',
                name: 'activity-log',
                component: () => import('../modules/activity-log/pages/ActivityLogPage.vue'),
                meta: { title: 'pageTitles.activity', permission: Permission.ViewActivityLog },
            },
        ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
];

export const router = createRouter({
    history: createWebHistory('/admin/'),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAdminAuthStore();

    try {
        await auth.loadUser();
    } catch {
        // Server unreachable: treat as logged out; the login page shows the error on submit.
    }

    if (to.meta.requiresAuth && !auth.isLoggedIn) {
        return { name: 'login', query: to.fullPath === '/' ? {} : { redirect: to.fullPath } };
    }

    if (to.meta.guestOnly && auth.isLoggedIn) {
        return { name: 'dashboard' };
    }

    // The API refuses the same requests; this just avoids opening a page that would only fail.
    if (to.meta.permission && !auth.can(to.meta.permission as PermissionName)) {
        return { name: 'dashboard' };
    }

    return true;
});
