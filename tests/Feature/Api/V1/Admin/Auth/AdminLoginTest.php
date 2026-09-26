<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withHeader;

it('logs staff in with a password and returns their roles', function (UserRole $role) {
    $user = User::factory()->withRole($role)->create(['phone_number' => '09121234567']);

    postJson(route('api.v1.admin.auth.login'), ['phone_number' => '09121234567', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.roles', [$role->value]);

    assertAuthenticatedAs($user);
})->with([
    'admin' => UserRole::Admin,
    'super admin' => UserRole::SuperAdmin,
]);

it('returns 403 and starts no session when a customer uses the admin password login', function () {
    User::factory()->withRole(UserRole::Customer)->create(['phone_number' => '09121234567']);

    withHeader('Accept-Language', 'en')
        ->postJson(route('api.v1.admin.auth.login'), ['phone_number' => '09121234567', 'password' => 'password'])
        ->assertForbidden()
        ->assertJsonPath('code', 'not_staff')
        ->assertJsonPath('message', 'You do not have access to the admin panel.');

    assertGuest();
});

it('logs an admin in with a login code', function () {
    $admin = User::factory()->withRole(UserRole::Admin)->create(['phone_number' => '09121234567']);

    postJson(route('api.v1.admin.auth.login.otp'), ['phone_number' => '09121234567'])->assertAccepted();

    postJson(route('api.v1.admin.auth.login.otp.verify'), [
        'phone_number' => '09121234567',
        'code' => sms()->lastCodeFor('09121234567'),
    ])
        ->assertOk()
        ->assertJsonPath('data.roles', ['admin']);

    assertAuthenticatedAs($admin);
});

it('returns 403 and starts no session when a customer uses the admin code login', function () {
    User::factory()->withRole(UserRole::Customer)->create(['phone_number' => '09121234567']);

    postJson(route('api.v1.admin.auth.login.otp'), ['phone_number' => '09121234567']);

    postJson(route('api.v1.admin.auth.login.otp.verify'), [
        'phone_number' => '09121234567',
        'code' => sms()->lastCodeFor('09121234567'),
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'not_staff');

    assertGuest();
});
