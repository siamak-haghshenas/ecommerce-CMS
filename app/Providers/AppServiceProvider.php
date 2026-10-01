<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\SmsSender;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Sms\SmsManager;
use App\Support\PhoneNumber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsManager::class);
        $this->app->bind(SmsSender::class, fn ($app) => $app->make(SmsManager::class)->driver());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn (User $user): ?bool => $user->hasRole(UserRole::SuperAdmin) ? true : null);

        Password::defaults(fn () => Password::min(8));

        $this->configureRouteBindings();
        $this->configureRateLimiting();
    }

    /**
     * Admin routes address customers and staff separately, so a customer id can never
     * reach a staff endpoint (or the reverse): it simply is not found.
     */
    private function configureRouteBindings(): void
    {
        Route::bind('customer', fn (string $id): User => User::query()->customers()->findOrFail($id));
        Route::bind('staffMember', fn (string $id): User => User::query()->staff()->findOrFail($id));
    }

    private function configureRateLimiting(): void
    {
        $phoneNumber = fn (Request $request): string => PhoneNumber::normalize($request->string('phone_number')->toString());

        RateLimiter::for('otp-send', fn (Request $request) => [
            Limit::perHour((int) config('otp.max_sends_per_hour_per_phone'))->by('phone:'.$phoneNumber($request)),
            Limit::perHour((int) config('otp.max_sends_per_hour_per_ip'))->by('ip:'.$request->ip()),
        ]);

        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinute((int) config('otp.max_verifications_per_minute'))
            ->by($phoneNumber($request).'|'.$request->ip()));

        RateLimiter::for('password-login', fn (Request $request) => Limit::perMinute(5)
            ->by($phoneNumber($request).'|'.$request->ip()));
    }
}
