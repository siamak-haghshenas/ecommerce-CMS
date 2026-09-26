<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\withHeader;

it('returns 401 for the current user when nobody is logged in', function () {
    withHeader('Accept-Language', 'en')
        ->getJson(route('api.v1.auth.me'))
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Please log in to continue.', 'code' => 'unauthenticated']);
});

it('returns only customer-safe fields for the current user', function () {
    $user = User::factory()->create(['email' => 'sara@example.com']);

    actingAs($user)
        ->getJson(route('api.v1.auth.me'))
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'phone_number' => $user->phone_number,
            'email' => 'sara@example.com',
            'locale' => 'fa',
        ]]);
});

it('logs the current user out', function () {
    actingAs(User::factory()->create())
        ->postJson(route('api.v1.auth.logout'))
        ->assertNoContent();

    assertGuest('web');
});
