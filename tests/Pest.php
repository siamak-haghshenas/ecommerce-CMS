<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Sms\Drivers\ArraySmsDriver;
use App\Services\Sms\SmsManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * The in-memory SMS driver used by the test environment (SMS_DRIVER=array).
 */
function sms(): ArraySmsDriver
{
    $driver = app(SmsManager::class)->driver('array');

    assert($driver instanceof ArraySmsDriver);

    return $driver;
}

/**
 * A code guaranteed to differ from the last code sent to the phone number.
 */
function wrongCodeFor(string $phoneNumber): string
{
    return sms()->lastCodeFor($phoneNumber) === '00000' ? '11111' : '00000';
}

/**
 * Call the storefront API the way an external storefront does: no cookies or
 * same-site Referer, only the Bearer token.
 *
 * @param  array<string, mixed>  $data
 */
function asStorefront(string $token, string $method, string $uri, array $data = []): TestResponse
{
    // Guards cache the resolved user between requests in one test; start clean.
    app('auth')->forgetGuards();

    return test()->flushHeaders()->withToken($token)->json($method, $uri, $data);
}

/**
 * Assert a login response carries a working Bearer token for the user.
 */
function assertIssuedTokenFor(TestResponse $response, User $user): string
{
    $response->assertJsonPath('data.token_type', 'Bearer')->assertJsonPath('data.customer.id', $user->id);

    $token = $response->json('data.token');
    expect($token)->toBeString()->not->toBeEmpty();

    asStorefront($token, 'GET', route('api.v1.auth.me'))->assertOk()->assertJsonPath('data.id', $user->id);

    return $token;
}

function assertNoTokenIssued(): void
{
    expect(PersonalAccessToken::query()->count())->toBe(0);
}
