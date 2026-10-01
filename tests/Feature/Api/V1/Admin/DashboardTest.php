<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\freezeTime;
use function Pest\Laravel\getJson;

it('returns 401 when nobody is logged in', function () {
    getJson(route('api.v1.admin.dashboard'))->assertUnauthorized();
});

it('returns 403 to roles that cannot view customers', function (UserRole $role) {
    actingAs(User::factory()->withRole($role)->create())
        ->getJson(route('api.v1.admin.dashboard'))
        ->assertForbidden();
})->with([
    'author' => UserRole::Author,
    'customer' => UserRole::Customer,
]);

it('counts customers, weekly sign-ups, unfinished sign-ups and staff', function () {
    freezeTime();
    $admin = User::factory()->withRole(UserRole::Admin)->create();
    User::factory()->withRole(UserRole::Author)->create();

    $customers = fn () => User::factory()->withRole(UserRole::Customer);
    $customers()->count(2)->create(['phone_verified_at' => now()->minus(days: 2)]);
    $customers()->create(['phone_verified_at' => now()->minus(days: 10)]);
    $customers()->create(['phone_verified_at' => now()->minus(days: 30)]);
    $customers()->unverified()->create();
    $customers()->create(['phone_verified_at' => now()->minus(days: 1), 'anonymised_at' => now(), 'phone_number' => null]);

    actingAs($admin)
        ->getJson(route('api.v1.admin.dashboard'))
        ->assertOk()
        ->assertJsonPath('data.stats', [
            'customers_total' => 4,
            'customers_new_this_week' => 2,
            'customers_new_previous_week' => 1,
            'pending_registrations' => 1,
            'staff_total' => 2,
        ])
        ->assertJsonCount(4, 'data.recent_customers');
});

it('lists the five most recently registered customers, newest first', function () {
    $customers = collect(range(1, 6))->map(
        fn (int $daysAgo) => User::factory()->withRole(UserRole::Customer)->create(['phone_verified_at' => now()->minus(days: $daysAgo)]),
    );

    actingAs(User::factory()->withRole(UserRole::Admin)->create())
        ->getJson(route('api.v1.admin.dashboard'))
        ->assertOk()
        ->assertJsonPath('data.recent_customers.*.id', $customers->take(5)->pluck('id')->all());
});
