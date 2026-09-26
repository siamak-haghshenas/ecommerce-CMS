<?php

declare(strict_types=1);

use App\Services\Sms\Drivers\ArraySmsDriver;
use App\Services\Sms\SmsManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
