<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

it('sends a login code and logs the customer in with it', function () {
    $user = User::factory()->create(['phone_number' => '09121234567']);

    postJson(route('api.v1.auth.login.otp'), ['phone_number' => '09121234567'])
        ->assertAccepted()
        ->assertJsonPath('data.phone_number', '09121234567');

    postJson(route('api.v1.auth.login.otp.verify'), [
        'phone_number' => '09121234567',
        'code' => sms()->lastCodeFor('09121234567'),
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);

    assertAuthenticatedAs($user);
});

it('responds identically without sending a code when the phone number is not registered', function (?callable $arrange) {
    if ($arrange !== null) {
        $arrange();
    }

    postJson(route('api.v1.auth.login.otp'), ['phone_number' => '09121234567'])
        ->assertAccepted()
        ->assertExactJson(['data' => [
            'phone_number' => '09121234567',
            'code_length' => 5,
            'expires_in' => 120,
            'resend_available_in' => 60,
        ]]);

    expect(sms()->countFor('09121234567'))->toBe(0);
})->with([
    'unknown phone number' => [null],
    'unfinished registration' => [fn () => User::factory()->unverified()->create(['phone_number' => '09121234567'])],
]);

it('applies the resend cooldown to unregistered phone numbers too', function () {
    postJson(route('api.v1.auth.login.otp'), ['phone_number' => '09121234567'])->assertAccepted();

    postJson(route('api.v1.auth.login.otp'), ['phone_number' => '09121234567'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'otp_resend_cooldown');
});

it('returns 403 after a correct code when the account is inactive', function () {
    User::factory()->inactive()->create(['phone_number' => '09121234567']);

    postJson(route('api.v1.auth.login.otp'), ['phone_number' => '09121234567']);

    postJson(route('api.v1.auth.login.otp.verify'), [
        'phone_number' => '09121234567',
        'code' => sms()->lastCodeFor('09121234567'),
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'account_inactive');

    assertGuest();
});

it('does not accept a password reset code for login', function () {
    User::factory()->create(['phone_number' => '09121234567']);

    postJson(route('api.v1.auth.password.otp'), ['phone_number' => '09121234567']);

    postJson(route('api.v1.auth.login.otp.verify'), [
        'phone_number' => '09121234567',
        'code' => sms()->lastCodeFor('09121234567'),
    ])->assertJsonPath('code', 'otp_invalid');

    assertGuest();
});

it('returns 429 after five codes are requested for one phone number in an hour', function () {
    User::factory()->create(['phone_number' => '09121234567']);

    foreach (range(1, 5) as $request) {
        postJson(route('api.v1.auth.login.otp'), ['phone_number' => '09121234567'])->assertAccepted();
        travel(61)->seconds();
    }

    postJson(route('api.v1.auth.login.otp'), ['phone_number' => '09121234567'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'too_many_requests');

    expect(sms()->countFor('09121234567'))->toBe(5);
});
