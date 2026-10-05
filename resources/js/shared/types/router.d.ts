import 'vue-router';

declare module 'vue-router' {
    interface RouteMeta {
        /** i18n key of the page title. */
        title?: string;
        requiresAuth?: boolean;
        guestOnly?: boolean;
        /** Permission name the page needs (admin app); a list: any one of them. */
        permission?: string | string[];
    }
}
