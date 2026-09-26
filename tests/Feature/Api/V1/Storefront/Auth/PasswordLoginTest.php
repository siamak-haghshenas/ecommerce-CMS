<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withHeader;

it('logs a customer in with the correct phone number and password', function () {
    $user = User::factory()->create(['phone_number' => '09121234567']);

    postJson(route('api.v1.auth.login'), ['phone_number' => '09121234567', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);

    assertAuthenticatedAs($user);
});

it('returns the same 422 error for a wrong password and an unknown phone number', function (string $phoneNumber, string $password) {
    User::factory()->create(['phone_number' => '09121234567']);

    withHeader('Accept-Language', 'en')
        ->postJson(route('api.v1.auth.login'), ['phone_number' => $phoneNumber, 'password' => $password])
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'The mobile number or password is incorrect.',
            'code' => 'invalid_credentials',
            'errors' => ['phone_number' => ['The mobile number or password is incorrect.']],
        ]);

    assertGuest();
})->with([
    'wrong password' => ['09121234567', 'wrong-password'],
    'unknown phone number' => ['09351234567', 'password'],
]);

it('returns 403 when the phone number has not been verified', function () {
    User::factory()->unverified()->create(['phone_number' => '09121234567']);

    postJson(route('api.v1.auth.login'), ['phone_number' => '09121234567', 'password' => 'password'])
        ->assertForbidden()
        ->assertJsonPath('code', 'phone_not_verified');

    assertGuest();
});

it('returns 403 when the account is inactive', function () {
    User::factory()->inactive()->create(['phone_number' => '09121234567']);

    postJson(route('api.v1.auth.login'), ['phone_number' => '09121234567', 'password' => 'password'])
        ->assertForbidden()
        ->assertJsonPath('code', 'account_inactive');

    assertGuest();
});

it('returns 429 after five failed attempts in a minute', function () {
    User::factory()->create(['phone_number' => '09121234567']);

    foreach (range(1, 5) as $attempt) {
        postJson(route('api.v1.auth.login'), ['phone_number' => '09121234567', 'password' => 'wrong-password'])
            ->assertUnprocessable();
    }

    postJson(route('api.v1.auth.login'), ['phone_number' => '09121234567', 'password' => 'password'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'too_many_requests');

    assertGuest();
});
