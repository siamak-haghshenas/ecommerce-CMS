<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\postJson;
use function Pest\Laravel\withHeader;

it('sets a new password, revokes every old token and returns a fresh one', function () {
    $user = User::factory()->create(['phone_number' => '09121234567']);
    $oldToken = $user->createToken('old phone')->plainTextToken;

    postJson(route('api.v1.auth.password.otp'), ['phone_number' => '09121234567'])->assertAccepted();

    $response = postJson(route('api.v1.auth.password.reset'), [
        'phone_number' => '09121234567',
        'code' => sms()->lastCodeFor('09121234567'),
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertOk();

    expect(Hash::check('brand-new-password', $user->refresh()->password))->toBeTrue();
    assertIssuedTokenFor($response, $user);
    asStorefront($oldToken, 'GET', route('api.v1.auth.me'))->assertUnauthorized();
});

it('responds identically without sending a code for an unknown phone number', function () {
    postJson(route('api.v1.auth.password.otp'), ['phone_number' => '09121234567'])
        ->assertAccepted()
        ->assertJsonPath('data.phone_number', '09121234567');

    expect(sms()->countFor('09121234567'))->toBe(0);
});

it('returns 422 and keeps the old password when the code is wrong', function () {
    $user = User::factory()->create(['phone_number' => '09121234567']);

    postJson(route('api.v1.auth.password.otp'), ['phone_number' => '09121234567']);

    postJson(route('api.v1.auth.password.reset'), [
        'phone_number' => '09121234567',
        'code' => wrongCodeFor('09121234567'),
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp_invalid');

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
    assertNoTokenIssued();
});

it('returns 422 when the new password is shorter than eight characters', function () {
    withHeader('Accept-Language', 'fa')
        ->postJson(route('api.v1.auth.password.reset'), [
            'phone_number' => '09121234567',
            'code' => '12345',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password' => 'رمز عبور باید حداقل 8 کاراکتر باشد.']);
});
