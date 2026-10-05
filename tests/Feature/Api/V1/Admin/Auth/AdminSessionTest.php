<?php

declare(strict_types=1);

use App\Enums\Permission;
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
        ->assertJsonPath('data.permissions', [
            'access-admin-panel',
            'customers.view',
            'customers.update',
            'customers.deactivate',
            'staff.view',
            'categories.view',
            'categories.manage',
            'blog_categories.manage',
            'brands.view',
            'brands.manage',
            'attributes.view',
            'attributes.manage',
            'products.view',
            'products.manage',
            'media.view',
            'media.upload',
            'media.manage',
            'pages.view',
            'pages.manage',
            'menus.view',
            'menus.manage',
            'posts.view',
            'posts.manage',
        ]);
});

it('lists every permission for a super admin, who holds none directly', function () {
    actingAs(User::factory()->withRole(UserRole::SuperAdmin)->create())
        ->getJson(route('api.v1.admin.auth.me'))
        ->assertOk()
        ->assertJsonPath('data.roles', ['super-admin'])
        ->assertJsonPath('data.permissions', array_map(fn (Permission $permission) => $permission->value, Permission::cases()));
});

it('gives an author panel access, media view/upload, pages, menus and the blog', function () {
    actingAs(User::factory()->withRole(UserRole::Author)->create())
        ->getJson(route('api.v1.admin.auth.me'))
        ->assertOk()
        ->assertJsonPath('data.permissions', ['access-admin-panel', 'blog_categories.manage', 'media.view', 'media.upload', 'pages.view', 'pages.manage', 'menus.view', 'menus.manage', 'posts.view', 'posts.manage']);
});

it('logs out a staff member who was deactivated while logged in', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->inactive()->create();

    actingAs($admin)
        ->getJson(route('api.v1.admin.auth.me'))
        ->assertForbidden()
        ->assertJsonPath('code', 'account_inactive');

    assertGuest('web');
});

it('logs the current staff member out', function () {
    actingAs(User::factory()->withRole(UserRole::Admin)->create())
        ->postJson(route('api.v1.admin.auth.logout'))
        ->assertNoContent();

    assertGuest('web');
});
