import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { store } from '../content';

/** Pages of the template that aren't built yet (frontstore.md › Pages). */
const comingSoon: [path: string, title: string][] = [
    ['/categories', 'دسته‌بندی کالاها'],
    ['/category/:slug', 'دسته‌بندی'],
    ['/product/:slug', 'کالا'],
    ['/brand/:slug', 'برند'],
    ['/search', 'جستجو'],
    ['/deals', 'پیشنهاد شگفت‌انگیز'],
    ['/sale/:slug', 'فروش ویژه'],
    ['/cart', 'سبد خرید'],
    ['/login', 'ورود یا ثبت‌نام'],
    ['/account', 'حساب من'],
    ['/blog', 'مجله'],
    ['/page/:slug', 'صفحه'],
];

const routes: RouteRecordRaw[] = [
    { path: '/', name: 'home', component: () => import('../pages/HomePage.vue') },
    ...comingSoon.map(([path, title]): RouteRecordRaw => ({
        path,
        component: () => import('../pages/ComingSoonPage.vue'),
        props: { title },
        meta: { storeTitle: title },
    })),
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('../pages/NotFoundPage.vue'),
        meta: { storeTitle: 'صفحه پیدا نشد' },
    },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: (_to, _from, saved) => saved ?? { top: 0 },
});

router.afterEach((to) => {
    const title = typeof to.meta.storeTitle === 'string' ? to.meta.storeTitle : null;
    document.title = title === null ? store.name : `${title} | ${store.name}`;
});
