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
                path: 'categories',
                name: 'categories',
                component: () => import('../modules/categories/pages/CategoriesPage.vue'),
                meta: {
                    title: 'pageTitles.categories',
                    permission: [Permission.ViewCategories, Permission.ManageBlogCategories],
                },
            },
            {
                path: 'brands',
                name: 'brands',
                component: () => import('../modules/brands/pages/BrandsPage.vue'),
                meta: { title: 'pageTitles.brands', permission: Permission.ViewBrands },
            },
            {
                path: 'attributes',
                name: 'attributes',
                component: () => import('../modules/attributes/pages/AttributesPage.vue'),
                meta: { title: 'pageTitles.attributes', permission: Permission.ViewAttributes },
            },
            {
                path: 'products',
                name: 'products',
                component: () => import('../modules/products/pages/ProductsPage.vue'),
                meta: { title: 'pageTitles.products', permission: Permission.ViewProducts },
            },
            {
                path: 'products/new',
                name: 'product-create',
                component: () => import('../modules/products/pages/ProductEditorPage.vue'),
                props: { id: null },
                meta: { title: 'pageTitles.productCreate', permission: Permission.ManageProducts },
            },
            {
                path: 'products/bulk-edit',
                name: 'products-bulk',
                component: () => import('../modules/products/pages/BulkEditPage.vue'),
                meta: { title: 'pageTitles.productsBulk', permission: Permission.ManageProducts },
            },
            {
                path: 'products/:id(\\d+)',
                name: 'product',
                component: () => import('../modules/products/pages/ProductEditorPage.vue'),
                props: (route) => ({ id: Number(route.params.id) }),
                meta: { title: 'pageTitles.product', permission: Permission.ViewProducts },
            },
            {
                path: 'pages',
                name: 'pages',
                component: () => import('../modules/pages/pages/PagesPage.vue'),
                meta: { title: 'pageTitles.pages', permission: Permission.ViewPages },
            },
            {
                path: 'pages/new',
                name: 'page-create',
                component: () => import('../modules/pages/pages/PageEditorPage.vue'),
                props: { id: null },
                meta: { title: 'pageTitles.pageCreate', permission: Permission.ManagePages },
            },
            {
                path: 'pages/:id(\\d+)',
                name: 'page',
                component: () => import('../modules/pages/pages/PageEditorPage.vue'),
                props: (route) => ({ id: Number(route.params.id) }),
                meta: { title: 'pageTitles.page', permission: Permission.ViewPages },
            },
            {
                path: 'blog',
                name: 'posts',
                component: () => import('../modules/posts/pages/PostsPage.vue'),
                meta: { title: 'pageTitles.posts', permission: Permission.ViewPosts },
            },
            {
                path: 'blog/new',
                name: 'post-create',
                component: () => import('../modules/posts/pages/PostEditorPage.vue'),
                props: { id: null },
                meta: { title: 'pageTitles.postCreate', permission: Permission.ManagePosts },
            },
            {
                path: 'blog/:id(\\d+)',
                name: 'post',
                component: () => import('../modules/posts/pages/PostEditorPage.vue'),
                props: (route) => ({ id: Number(route.params.id) }),
                meta: { title: 'pageTitles.post', permission: Permission.ViewPosts },
            },
            {
                path: 'discounts',
                name: 'discounts',
                component: () => import('../modules/discounts/pages/DiscountsPage.vue'),
                meta: { title: 'pageTitles.discounts', permission: Permission.ViewDiscounts },
            },
            {
                path: 'discounts/promotions/new',
                name: 'promotion-create',
                component: () => import('../modules/discounts/pages/PromotionEditorPage.vue'),
                props: { id: null },
                meta: { title: 'pageTitles.promotionCreate', permission: Permission.ManageDiscounts },
            },
            {
                path: 'discounts/promotions/:id(\\d+)',
                name: 'promotion',
                component: () => import('../modules/discounts/pages/PromotionEditorPage.vue'),
                props: (route) => ({ id: Number(route.params.id) }),
                meta: { title: 'pageTitles.promotion', permission: Permission.ViewDiscounts },
            },
            {
                path: 'menus',
                name: 'menus',
                component: () => import('../modules/menus/pages/MenusPage.vue'),
                meta: { title: 'pageTitles.menus', permission: Permission.ViewMenus },
            },
            {
                path: 'media',
                name: 'media',
                component: () => import('../modules/media/pages/MediaPage.vue'),
                meta: { title: 'pageTitles.media', permission: Permission.ViewMedia },
            },
            {
                path: 'tags',
                name: 'tags',
                component: () => import('../modules/products/pages/TagsPage.vue'),
                meta: { title: 'pageTitles.tags', permission: Permission.ViewProducts },
            },
            {
                path: 'settings',
                name: 'settings',
                component: () => import('../modules/settings/pages/SettingsPage.vue'),
                meta: { title: 'pageTitles.settings' },
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
    if (to.meta.permission && !auth.canAny(to.meta.permission as PermissionName | PermissionName[])) {
        return { name: 'dashboard' };
    }

    return true;
});
