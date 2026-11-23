import 'vue-router';

declare module 'vue-router' {
    interface RouteMeta {
        /** Storefront: the page's title (Farsi text, not an i18n key). */
        storeTitle?: string;
    }
}
