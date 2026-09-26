<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\getJson;

it('returns 401 for the current staff member when nobody is logged in', function () {
    getJson(route('api.v1.admin.auth.me'))
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

it('returns 403 when a logged-in customer calls the admin API', function () {
    actingAs(User::factory()->withRole(UserRole::Customer)->create())
        ->getJson(route('api.v1.admin.auth.me'))
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');
});

it('returns the current staff member with roles and permissions', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create();

    actingAs($admin)
        ->getJson(route('api.v1.admin.auth.me'))
        ->assertOk()
        ->assertJsonPath('data.id', $admin->id)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.roles', ['admin'])
        ->assertJsonPath('data.permissions', ['access-admin-panel']);
});

it('lets a super admin use the admin API without explicit permissions', function () {
    actingAs(User::factory()->withRole(UserRole::SuperAdmin)->create())
        ->getJson(route('api.v1.admin.auth.me'))
        ->assertOk()
        ->assertJsonPath('data.roles', ['super-admin']);
});

it('logs the current staff member out', function () {
    actingAs(User::factory()->withRole(UserRole::Admin)->create())
        ->postJson(route('api.v1.admin.auth.logout'))
        ->assertNoContent();

    assertGuest('web');
});
