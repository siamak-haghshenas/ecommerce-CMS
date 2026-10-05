/**
 * Mirrors App\Enums\Permission and App\Enums\UserRole on the backend.
 * The backend stays the authority: these only decide what the UI shows.
 */
export const Permission = {
    AccessAdminPanel: 'access-admin-panel',
    ViewCustomers: 'customers.view',
    UpdateCustomers: 'customers.update',
    DeactivateCustomers: 'customers.deactivate',
    AnonymiseCustomers: 'customers.anonymise',
    ViewStaff: 'staff.view',
    ManageStaff: 'staff.manage',
    ViewActivityLog: 'activity-log.view',
    ViewCategories: 'categories.view',
    ManageCategories: 'categories.manage',
    ManageBlogCategories: 'blog_categories.manage',
    ViewBrands: 'brands.view',
    ManageBrands: 'brands.manage',
    ViewAttributes: 'attributes.view',
    ManageAttributes: 'attributes.manage',
    ViewProducts: 'products.view',
    ManageProducts: 'products.manage',
    ViewMedia: 'media.view',
    UploadMedia: 'media.upload',
    ManageMedia: 'media.manage',
    ViewPages: 'pages.view',
    ManagePages: 'pages.manage',
    ViewMenus: 'menus.view',
    ManageMenus: 'menus.manage',
    ViewPosts: 'posts.view',
    ManagePosts: 'posts.manage',
    ManageSettings: 'settings.manage',
} as const;

export type PermissionName = (typeof Permission)[keyof typeof Permission];

/** Fixed staff roles; there is no role editor. */
export const STAFF_ROLES = ['super-admin', 'admin', 'author'] as const;

export type StaffRole = (typeof STAFF_ROLES)[number];

/** i18n key segment per role: admin.roleNames.<key>, admin.roleDescriptions.<key>. */
export const ROLE_KEYS: Record<StaffRole, 'superAdmin' | 'admin' | 'author'> = {
    'super-admin': 'superAdmin',
    admin: 'admin',
    author: 'author',
};
