<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Admin\ActivityLogController;
use App\Http\Controllers\Api\V1\Admin\Auth as AdminAuth;
use App\Http\Controllers\Api\V1\Admin\Customers\CustomerAnonymisationController;
use App\Http\Controllers\Api\V1\Admin\Customers\CustomerController;
use App\Http\Controllers\Api\V1\Admin\Customers\CustomerStatusController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\Staff\StaffController;
use App\Http\Controllers\Api\V1\Admin\Staff\StaffRoleController;
use App\Http\Controllers\Api\V1\Admin\Staff\StaffStatusController;
use App\Http\Controllers\Api\V1\Storefront\Auth as StorefrontAuth;
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

            Route::get('activity-log', ActivityLogController::class)
                ->middleware(PermissionMiddleware::using(Permission::ViewActivityLog))
                ->name('activity-log');
        });
    });
});
