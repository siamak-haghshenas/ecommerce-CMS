<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\withHeader;

it('returns 401 for the current customer without a token', function () {
    withHeader('Accept-Language', 'en')
        ->getJson(route('api.v1.auth.me'))
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Please log in to continue.', 'code' => 'unauthenticated']);
});

it('returns 401 for an expired token', function () {
    $token = User::factory()->create()->createToken('storefront', ['customer'], now()->minus(minutes: 1))->plainTextToken;

    asStorefront($token, 'GET', route('api.v1.auth.me'))->assertUnauthorized();
});

it('returns only customer-safe fields for the token owner', function () {
    $user = User::factory()->create(['email' => 'sara@example.com']);
    $token = $user->createToken('storefront')->plainTextToken;

    asStorefront($token, 'GET', route('api.v1.auth.me'))
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'phone_number' => $user->phone_number,
            'email' => 'sara@example.com',
            'locale' => 'fa',
        ]]);
});

it('logs out by revoking only the token used, keeping other devices logged in', function () {
    $user = User::factory()->create();
    $webToken = $user->createToken('web')->plainTextToken;
    $appToken = $user->createToken('app')->plainTextToken;

    asStorefront($webToken, 'POST', route('api.v1.auth.logout'))->assertNoContent();

    asStorefront($webToken, 'GET', route('api.v1.auth.me'))->assertUnauthorized();
    asStorefront($appToken, 'GET', route('api.v1.auth.me'))->assertOk();
});

it('rejects the token of a customer deactivated after logging in', function () {
    $user = User::factory()->create();
    $token = $user->createToken('storefront')->plainTextToken;
    $user->forceFill(['is_active' => false])->save();

    asStorefront($token, 'GET', route('api.v1.auth.me'))
        ->assertForbidden()
        ->assertJsonPath('code', 'account_inactive');
});

it('allows a configured external storefront origin (CORS)', function () {
    config(['cors.allowed_origins' => ['https://shop.example.com']]);

    test()->flushHeaders()
        ->withHeaders(['Origin' => 'https://shop.example.com', 'Access-Control-Request-Method' => 'POST'])
        ->options(route('api.v1.auth.login'))
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', 'https://shop.example.com');
});
