<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Admin\Account\BylineController;
use App\Http\Controllers\Api\V1\Admin\ActivityLogController;
use App\Http\Controllers\Api\V1\Admin\Auth as AdminAuth;
use App\Http\Controllers\Api\V1\Admin\Brands\BrandController as AdminBrandController;
use App\Http\Controllers\Api\V1\Admin\Brands\BrandOrderController;
use App\Http\Controllers\Api\V1\Admin\Catalog\ApplicableAttributeController;
use App\Http\Controllers\Api\V1\Admin\Catalog\AttributeController;
use App\Http\Controllers\Api\V1\Admin\Catalog\OptionController;
use App\Http\Controllers\Api\V1\Admin\Categories\CategoryAttributeController;
use App\Http\Controllers\Api\V1\Admin\Categories\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\Categories\CategoryOrderController;
use App\Http\Controllers\Api\V1\Admin\Customers\CustomerAnonymisationController;
use App\Http\Controllers\Api\V1\Admin\Customers\CustomerController;
use App\Http\Controllers\Api\V1\Admin\Customers\CustomerStatusController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\Media\MediaAssetController;
use App\Http\Controllers\Api\V1\Admin\Media\MediaFolderController;
use App\Http\Controllers\Api\V1\Admin\Media\MediaUploadController;
use App\Http\Controllers\Api\V1\Admin\Menus\MenuController as AdminMenuController;
use App\Http\Controllers\Api\V1\Admin\Menus\MenuTargetController;
use App\Http\Controllers\Api\V1\Admin\Pages\PageAutosaveController;
use App\Http\Controllers\Api\V1\Admin\Pages\PageController;
use App\Http\Controllers\Api\V1\Admin\Pages\PagePreviewController;
use App\Http\Controllers\Api\V1\Admin\Pages\PageRevisionController;
use App\Http\Controllers\Api\V1\Admin\Posts\PostAuthorController;
use App\Http\Controllers\Api\V1\Admin\Posts\PostAutosaveController;
use App\Http\Controllers\Api\V1\Admin\Posts\PostController;
use App\Http\Controllers\Api\V1\Admin\Posts\PostPreviewController;
use App\Http\Controllers\Api\V1\Admin\Posts\PostRevisionController;
use App\Http\Controllers\Api\V1\Admin\Products\BulkProductController;
use App\Http\Controllers\Api\V1\Admin\Products\ProductController;
use App\Http\Controllers\Api\V1\Admin\Products\StockMovementController;
use App\Http\Controllers\Api\V1\Admin\Products\TagController;
use App\Http\Controllers\Api\V1\Admin\Promotions\PromotionController;
use App\Http\Controllers\Api\V1\Admin\SaleEvents\SaleEventController;
use App\Http\Controllers\Api\V1\Admin\SaleEvents\SaleEventOrderController;
use App\Http\Controllers\Api\V1\Admin\SaleEvents\SaleEventPreviewController;
use App\Http\Controllers\Api\V1\Admin\SaleEvents\SaleEventVariantController;
use App\Http\Controllers\Api\V1\Admin\SeoSuggestionController;
use App\Http\Controllers\Api\V1\Admin\SettingsController;
use App\Http\Controllers\Api\V1\Admin\Staff\StaffController;
use App\Http\Controllers\Api\V1\Admin\Staff\StaffRoleController;
use App\Http\Controllers\Api\V1\Admin\Staff\StaffStatusController;
use App\Http\Controllers\Api\V1\Storefront\Auth as StorefrontAuth;
use App\Http\Controllers\Api\V1\Storefront\Blog\BlogCategoryController;
use App\Http\Controllers\Api\V1\Storefront\Blog\PostController as StorefrontPostController;
use App\Http\Controllers\Api\V1\Storefront\Catalog\BrandController;
use App\Http\Controllers\Api\V1\Storefront\Catalog\CategoryController;
use App\Http\Controllers\Api\V1\Storefront\Catalog\ProductController as StorefrontProductController;
use App\Http\Controllers\Api\V1\Storefront\Catalog\SaleEventController as StorefrontSaleEventController;
use App\Http\Controllers\Api\V1\Storefront\Content\MenuController as StorefrontMenuController;
use App\Http\Controllers\Api\V1\Storefront\Content\PageController as StorefrontPageController;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::prefix('v1')->name('api.v1.')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Storefront
    |--------------------------------------------------------------------------
    */

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::middleware('throttle:otp-send')->group(function () {
            Route::post('register', StorefrontAuth\RegisterController::class)->name('register');
            Route::post('register/otp', StorefrontAuth\ResendRegistrationOtpController::class)->name('register.otp');
            Route::post('login/otp', StorefrontAuth\SendLoginOtpController::class)->name('login.otp');
            Route::post('password/otp', StorefrontAuth\SendPasswordResetOtpController::class)->name('password.otp');
        });

        Route::middleware('throttle:otp-verify')->group(function () {
            Route::post('register/verify', StorefrontAuth\VerifyRegistrationController::class)->name('register.verify');
            Route::post('login/otp/verify', StorefrontAuth\LoginWithOtpController::class)->name('login.otp.verify');
            Route::post('password/reset', StorefrontAuth\ResetPasswordController::class)->name('password.reset');
        });

        Route::post('login', StorefrontAuth\LoginWithPasswordController::class)
            ->middleware('throttle:password-login')
            ->name('login');

        Route::middleware(['auth:sanctum', 'active'])->group(function () {
            Route::get('me', [StorefrontAuth\SessionController::class, 'show'])->name('me');
            Route::post('logout', [StorefrontAuth\SessionController::class, 'destroy'])->name('logout');
        });
    });

    // Catalog: public and read-only. Hidden categories (or ones under a hidden parent) are never returned.
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/{slug}', [CategoryController::class, 'show'])->name('categories.show');

    // Blog: public and read-only.
    Route::prefix('blog')->name('blog.')->group(function () {
        Route::get('categories', [BlogCategoryController::class, 'index'])->name('categories.index');
        Route::get('categories/{slug}', [BlogCategoryController::class, 'show'])->name('categories.show');
        Route::get('posts', [StorefrontPostController::class, 'index'])->name('posts.index');
        // A staff member's preview (drafts, scheduled posts, unsaved changes); the token is signed and expires.
        Route::get('posts/preview/{token}', [StorefrontPostController::class, 'preview'])
            ->middleware('throttle:60,1')
            ->name('posts.preview');
        Route::get('posts/{slug}', [StorefrontPostController::class, 'show'])->name('posts.show');
    });
    Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
    Route::get('brands/{slug}', [BrandController::class, 'show'])->name('brands.show');
    Route::get('products', [StorefrontProductController::class, 'index'])->name('products.index');
    Route::get('products/filters', [StorefrontProductController::class, 'filters'])->name('products.filters');
    Route::get('products/{slug}', [StorefrontProductController::class, 'show'])->name('products.show');
    // Sale event landing pages (data only); their products come from `products?sale_event={slug}`.
    Route::get('sale-events', [StorefrontSaleEventController::class, 'index'])->name('sale-events.index');
    Route::get('sale-events/preview/{token}', [StorefrontSaleEventController::class, 'preview'])
        ->middleware('throttle:60,1')
        ->name('sale-events.preview');
    Route::get('sale-events/{slug}', [StorefrontSaleEventController::class, 'show'])->name('sale-events.show');

    // Content: published pages only.
    Route::get('pages', [StorefrontPageController::class, 'index'])->name('pages.index');
    // A staff member's preview (drafts, scheduled pages, unsaved changes); the token is signed and expires.
    Route::get('pages/preview/{token}', [StorefrontPageController::class, 'preview'])
        ->middleware('throttle:60,1')
        ->name('pages.preview');
    Route::get('pages/{slug}', [StorefrontPageController::class, 'show'])->name('pages.show');
    Route::get('menus/{location}', [StorefrontMenuController::class, 'show'])->name('menus.show');

    /*
    |--------------------------------------------------------------------------
    | Admin
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('login', AdminAuth\LoginWithPasswordController::class)
                ->middleware('throttle:password-login')
                ->name('login');
            Route::post('login/otp', AdminAuth\SendLoginOtpController::class)
                ->middleware('throttle:otp-send')
                ->name('login.otp');
            Route::post('login/otp/verify', AdminAuth\LoginWithOtpController::class)
                ->middleware('throttle:otp-verify')
                ->name('login.otp.verify');
        });

        Route::middleware(['auth:sanctum', 'active', PermissionMiddleware::using(Permission::AccessAdminPanel)])->group(function () {
            Route::get('auth/me', [AdminAuth\SessionController::class, 'show'])->name('auth.me');
            Route::post('auth/logout', [AdminAuth\SessionController::class, 'destroy'])->name('auth.logout');

            // Customer figures and names, so it needs the same permission as the customer list.
            Route::get('dashboard', DashboardController::class)
                ->middleware(PermissionMiddleware::using(Permission::ViewCustomers))
                ->name('dashboard');

            // Customers: {customer} only resolves users with the customer role (AppServiceProvider).
            Route::prefix('customers')->name('customers.')->group(function () {
                Route::get('/', [CustomerController::class, 'index'])
                    ->middleware(PermissionMiddleware::using(Permission::ViewCustomers))
                    ->name('index');
                Route::get('{customer}', [CustomerController::class, 'show'])
                    ->middleware(PermissionMiddleware::using(Permission::ViewCustomers))
                    ->name('show');
                Route::put('{customer}', [CustomerController::class, 'update'])
                    ->middleware(PermissionMiddleware::using(Permission::UpdateCustomers))
                    ->name('update');
                Route::put('{customer}/status', CustomerStatusController::class)
                    ->middleware(PermissionMiddleware::using(Permission::DeactivateCustomers))
                    ->name('status');
                Route::post('{customer}/anonymise', CustomerAnonymisationController::class)
                    ->middleware(PermissionMiddleware::using(Permission::AnonymiseCustomers))
                    ->name('anonymise');
            });

            // Staff: {staffMember} only resolves users with a staff role (AppServiceProvider).
            Route::prefix('staff')->name('staff.')->group(function () {
                Route::get('/', [StaffController::class, 'index'])
                    ->middleware(PermissionMiddleware::using(Permission::ViewStaff))
                    ->name('index');

                Route::middleware(PermissionMiddleware::using(Permission::ManageStaff))->group(function () {
                    Route::post('/', [StaffController::class, 'store'])->name('store');
                    Route::put('{staffMember}/role', StaffRoleController::class)->name('role');
                    Route::put('{staffMember}/status', StaffStatusController::class)->name('status');
                    Route::delete('{staffMember}', [StaffController::class, 'destroy'])->name('destroy');
                });
            });

            // Categories: any depth; the tree rules live in the category actions and CategoryTree.
            // Product and blog categories need different permissions, checked per type by the
            // requests and controllers (CategoryType::viewPermission() / managePermission()).
            Route::prefix('categories')->name('categories.')->group(function () {
                Route::get('/', [AdminCategoryController::class, 'index'])->name('index');
                Route::get('{category}', [AdminCategoryController::class, 'show'])->whereNumber('category')->name('show');
                Route::post('/', [AdminCategoryController::class, 'store'])->name('store');
                Route::put('order', CategoryOrderController::class)->name('order');
                Route::put('{category}', [AdminCategoryController::class, 'update'])->whereNumber('category')->name('update');
                Route::delete('{category}', [AdminCategoryController::class, 'destroy'])->whereNumber('category')->name('destroy');

                // Specifications: product categories only.
                Route::put('{category}/attributes', [CategoryAttributeController::class, 'update'])
                    ->whereNumber('category')
                    ->middleware(PermissionMiddleware::using(Permission::ManageCategories))
                    ->name('attributes.update');

                Route::get('{category}/attributes', [CategoryAttributeController::class, 'show'])
                    ->whereNumber('category')
                    ->middleware(PermissionMiddleware::using(Permission::ViewCategories))
                    ->name('attributes.show');
            });

            // Attributes (specifications) and options (variant dimensions): flat lists in manual order.
            foreach (['attributes' => [AttributeController::class, 'attribute'], 'options' => [OptionController::class, 'option']] as $prefix => [$controller, $parameter]) {
                Route::prefix($prefix)->name("{$prefix}.")->group(function () use ($controller, $parameter) {
                    Route::get('/', [$controller, 'index'])
                        ->middleware(PermissionMiddleware::using(Permission::ViewAttributes))
                        ->name('index');

                    Route::middleware(PermissionMiddleware::using(Permission::ManageAttributes))->group(function () use ($controller, $parameter) {
                        Route::post('/', [$controller, 'store'])->name('store');
                        Route::put('order', [$controller, 'reorder'])->name('order');
                        Route::put("{{$parameter}}", [$controller, 'update'])->whereNumber($parameter)->name('update');
                        Route::delete("{{$parameter}}", [$controller, 'destroy'])->whereNumber($parameter)->name('destroy');
                    });
                });
            }

            // Brands: a flat list in manual order; the list is never paginated.
            Route::prefix('brands')->name('brands.')->group(function () {
                Route::middleware(PermissionMiddleware::using(Permission::ViewBrands))->group(function () {
                    Route::get('/', [AdminBrandController::class, 'index'])->name('index');
                    Route::get('{brand}', [AdminBrandController::class, 'show'])->whereNumber('brand')->name('show');
                });

                Route::middleware(PermissionMiddleware::using(Permission::ManageBrands))->group(function () {
                    Route::post('/', [AdminBrandController::class, 'store'])->name('store');
                    Route::put('order', BrandOrderController::class)->name('order');
                    Route::put('{brand}', [AdminBrandController::class, 'update'])->whereNumber('brand')->name('update');
                    Route::delete('{brand}', [AdminBrandController::class, 'destroy'])->whereNumber('brand')->name('destroy');
                });
            });

            // Products. {product} also resolves deleted products, so they can be viewed and restored.
            Route::prefix('products')->name('products.')->group(function () {
                Route::middleware(PermissionMiddleware::using(Permission::ViewProducts))->group(function () {
                    Route::get('/', [ProductController::class, 'index'])->name('index');
                    Route::get('ids', [ProductController::class, 'ids'])->name('ids');
                    Route::get('{product}', [ProductController::class, 'show'])->whereNumber('product')->withTrashed()->name('show');
                    Route::get('{product}/stock-movements', StockMovementController::class)->whereNumber('product')->withTrashed()->name('stock-movements');
                });

                Route::middleware(PermissionMiddleware::using(Permission::ManageProducts))->group(function () {
                    Route::post('/', [ProductController::class, 'store'])->name('store');
                    Route::post('status', [ProductController::class, 'changeStatus'])->name('status');
                    Route::get('bulk', [BulkProductController::class, 'index'])->name('bulk.index');
                    Route::put('bulk', [BulkProductController::class, 'update'])->name('bulk.update');
                    Route::put('{product}', [ProductController::class, 'update'])->whereNumber('product')->name('update');
                    Route::delete('{product}', [ProductController::class, 'destroy'])->whereNumber('product')->name('destroy');
                    Route::post('{product}/restore', [ProductController::class, 'restore'])->whereNumber('product')->withTrashed()->name('restore');
                });
            });

            // Blog posts. {post} also resolves posts in the trash, so they can be viewed and restored.
            Route::prefix('posts')->name('posts.')->group(function () {
                Route::middleware(PermissionMiddleware::using(Permission::ViewPosts))->group(function () {
                    Route::get('/', [PostController::class, 'index'])->name('index');
                    Route::get('authors', PostAuthorController::class)->name('authors');
                    Route::get('{post}', [PostController::class, 'show'])->whereNumber('post')->withTrashed()->name('show');
                    Route::get('{post}/revisions', [PostRevisionController::class, 'index'])->whereNumber('post')->withTrashed()->name('revisions.index');
                    Route::get('{post}/revisions/{revision}', [PostRevisionController::class, 'show'])
                        ->whereNumber(['post', 'revision'])->withTrashed()->scopeBindings()->name('revisions.show');
                    Route::post('{post}/preview', PostPreviewController::class)->whereNumber('post')->name('preview');
                });

                Route::middleware(PermissionMiddleware::using(Permission::ManagePosts))->group(function () {
                    Route::post('/', [PostController::class, 'store'])->name('store');
                    Route::put('{post}', [PostController::class, 'update'])->whereNumber('post')->name('update');
                    Route::delete('{post}', [PostController::class, 'destroy'])->whereNumber('post')->name('destroy');
                    Route::post('{post}/restore', [PostController::class, 'restore'])->whereNumber('post')->withTrashed()->name('restore');
                    Route::put('{post}/autosave', [PostAutosaveController::class, 'update'])->whereNumber('post')->name('autosave.update');
                    Route::delete('{post}/autosave', [PostAutosaveController::class, 'destroy'])->whereNumber('post')->name('autosave.destroy');
                });
            });

            // The signed-in staff member's own public author byline.
            Route::get('byline', [BylineController::class, 'show'])->name('byline.show');
            Route::put('byline', [BylineController::class, 'update'])->name('byline.update');

            // Pages. {page} also resolves pages in the trash, so they can be viewed and restored.
            Route::prefix('pages')->name('pages.')->group(function () {
                Route::middleware(PermissionMiddleware::using(Permission::ViewPages))->group(function () {
                    Route::get('/', [PageController::class, 'index'])->name('index');
                    Route::get('{page}', [PageController::class, 'show'])->whereNumber('page')->withTrashed()->name('show');
                    Route::get('{page}/revisions', [PageRevisionController::class, 'index'])->whereNumber('page')->withTrashed()->name('revisions.index');
                    Route::get('{page}/revisions/{revision}', [PageRevisionController::class, 'show'])
                        ->whereNumber(['page', 'revision'])->withTrashed()->scopeBindings()->name('revisions.show');
                    Route::post('{page}/preview', PagePreviewController::class)->whereNumber('page')->name('preview');
                });

                Route::middleware(PermissionMiddleware::using(Permission::ManagePages))->group(function () {
                    Route::post('/', [PageController::class, 'store'])->name('store');
                    Route::put('{page}', [PageController::class, 'update'])->whereNumber('page')->name('update');
                    Route::delete('{page}', [PageController::class, 'destroy'])->whereNumber('page')->name('destroy');
                    Route::post('{page}/restore', [PageController::class, 'restore'])->whereNumber('page')->withTrashed()->name('restore');
                    Route::put('{page}/autosave', [PageAutosaveController::class, 'update'])->whereNumber('page')->name('autosave.update');
                    Route::delete('{page}/autosave', [PageAutosaveController::class, 'destroy'])->whereNumber('page')->name('autosave.destroy');
                });
            });

            // The header and footer menus, each saved as a whole.
            Route::prefix('menus')->name('menus.')->group(function () {
                Route::get('targets', MenuTargetController::class)
                    ->middleware(PermissionMiddleware::using(Permission::ManageMenus))
                    ->name('targets');
                Route::get('{location}', [AdminMenuController::class, 'show'])
                    ->middleware(PermissionMiddleware::using(Permission::ViewMenus))
                    ->name('show');
                Route::put('{location}', [AdminMenuController::class, 'update'])
                    ->middleware(PermissionMiddleware::using(Permission::ManageMenus))
                    ->name('update');
            });

            // Promotions: automatic catalog discounts (saving reprices the catalog).
            Route::prefix('promotions')->name('promotions.')->group(function () {
                Route::middleware(PermissionMiddleware::using(Permission::ViewDiscounts))->group(function () {
                    Route::get('/', [PromotionController::class, 'index'])->name('index');
                    Route::get('{promotion}', [PromotionController::class, 'show'])->whereNumber('promotion')->name('show');
                });

                Route::middleware(PermissionMiddleware::using(Permission::ManageDiscounts))->group(function () {
                    Route::post('/', [PromotionController::class, 'store'])->name('store');
                    Route::post('preview', [PromotionController::class, 'preview'])->name('preview');
                    Route::put('{promotion}', [PromotionController::class, 'update'])->whereNumber('promotion')->name('update');
                    Route::delete('{promotion}', [PromotionController::class, 'destroy'])->whereNumber('promotion')->name('destroy');
                });
            });

            // Sale events: time-limited sales on chosen variants, in priority order (saving reprices their products).
            Route::prefix('sale-events')->name('sale-events.')->group(function () {
                Route::middleware(PermissionMiddleware::using(Permission::ViewDiscounts))->group(function () {
                    Route::get('/', [SaleEventController::class, 'index'])->name('index');
                    Route::get('{saleEvent}', [SaleEventController::class, 'show'])->whereNumber('saleEvent')->name('show');
                    Route::post('{saleEvent}/preview', SaleEventPreviewController::class)->whereNumber('saleEvent')->name('preview');
                });

                Route::middleware(PermissionMiddleware::using(Permission::ManageDiscounts))->group(function () {
                    Route::post('/', [SaleEventController::class, 'store'])->name('store');
                    Route::put('order', SaleEventOrderController::class)->name('order');
                    Route::post('variants', SaleEventVariantController::class)->name('variants');
                    Route::put('{saleEvent}', [SaleEventController::class, 'update'])->whereNumber('saleEvent')->name('update');
                    Route::delete('{saleEvent}', [SaleEventController::class, 'destroy'])->whereNumber('saleEvent')->name('destroy');
                });
            });

            // The specification fields of the product editor for the chosen categories.
            Route::get('attributes/applicable', ApplicableAttributeController::class)
                ->middleware(PermissionMiddleware::using(Permission::ViewProducts))
                ->name('attributes.applicable');

            // Media library. {mediaAsset} resolves files in the trash for show/restore only.
            Route::prefix('media')->name('media.')->group(function () {
                Route::middleware(PermissionMiddleware::using(Permission::ViewMedia))->group(function () {
                    Route::get('/', [MediaAssetController::class, 'index'])->name('index');
                    Route::get('folders', [MediaFolderController::class, 'index'])->name('folders.index');
                    Route::get('{mediaAsset}', [MediaAssetController::class, 'show'])->whereNumber('mediaAsset')->withTrashed()->name('show');
                });

                Route::middleware(PermissionMiddleware::using(Permission::UploadMedia))->group(function () {
                    Route::post('uploads', [MediaUploadController::class, 'store'])->name('uploads.store');
                    Route::post('uploads/{mediaUpload}/chunks', [MediaUploadController::class, 'chunk'])->name('uploads.chunk');
                    Route::post('uploads/{mediaUpload}/complete', [MediaUploadController::class, 'complete'])->name('uploads.complete');
                    Route::delete('uploads/{mediaUpload}', [MediaUploadController::class, 'destroy'])->name('uploads.destroy');
                    Route::put('{mediaAsset}', [MediaAssetController::class, 'update'])->whereNumber('mediaAsset')->name('update');
                });

                Route::middleware(PermissionMiddleware::using(Permission::ManageMedia))->group(function () {
                    Route::post('move', [MediaAssetController::class, 'move'])->name('move');
                    Route::delete('{mediaAsset}', [MediaAssetController::class, 'destroy'])->whereNumber('mediaAsset')->name('destroy');
                    Route::post('{mediaAsset}/restore', [MediaAssetController::class, 'restore'])->whereNumber('mediaAsset')->withTrashed()->name('restore');
                    Route::post('folders', [MediaFolderController::class, 'store'])->name('folders.store');
                    Route::put('folders/{mediaFolder}', [MediaFolderController::class, 'update'])->whereNumber('mediaFolder')->name('folders.update');
                    Route::delete('folders/{mediaFolder}', [MediaFolderController::class, 'destroy'])->whereNumber('mediaFolder')->name('folders.destroy');
                });
            });

            Route::prefix('tags')->name('tags.')->group(function () {
                // Tags are shared by products and posts: either permission lists them (suggestions).
                Route::get('/', [TagController::class, 'index'])
                    ->middleware(PermissionMiddleware::using([Permission::ViewProducts->value, Permission::ViewPosts->value]))
                    ->name('index');

                Route::middleware(PermissionMiddleware::using(Permission::ManageProducts))->group(function () {
                    Route::put('{tag}', [TagController::class, 'update'])->whereNumber('tag')->name('update');
                    Route::post('{tag}/merge', [TagController::class, 'merge'])->whereNumber('tag')->name('merge');
                    Route::delete('{tag}', [TagController::class, 'destroy'])->whereNumber('tag')->name('destroy');
                });
            });

            Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
            Route::put('settings', [SettingsController::class, 'update'])
                ->middleware(PermissionMiddleware::using(Permission::ManageSettings))
                ->name('settings.update');

            // Shared by every content type with SEO fields. Paid AI calls: limited per user and
            // store-wide (rate limiter "ai"); the request checks the content type's manage permission.
            Route::post('seo-suggestions', SeoSuggestionController::class)
                ->middleware('throttle:ai')
                ->name('seo-suggestions');

            Route::get('activity-log', ActivityLogController::class)
                ->middleware(PermissionMiddleware::using(Permission::ViewActivityLog))
                ->name('activity-log');
        });
    });
});
