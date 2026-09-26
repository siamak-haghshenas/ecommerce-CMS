<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Admin\Auth as AdminAuth;
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

        Route::middleware('auth:sanctum')->group(function () {
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

        Route::middleware(['auth:sanctum', PermissionMiddleware::using(Permission::AccessAdminPanel)])->group(function () {
            Route::get('auth/me', [AdminAuth\SessionController::class, 'show'])->name('auth.me');
            Route::post('auth/logout', [AdminAuth\SessionController::class, 'destroy'])->name('auth.logout');
        });
    });
});
