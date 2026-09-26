<?php

declare(strict_types=1);

use App\Support\PhoneNumber;

it('normalizes Iranian mobile numbers to the 09xxxxxxxxx format', function (string $input) {
    expect(PhoneNumber::normalize($input))->toBe('09121234567');
})->with([
    'already normalized' => '09121234567',
    'persian digits' => '۰۹۱۲۱۲۳۴۵۶۷',
    'arabic digits' => '٠٩١٢١٢٣٤٥٦٧',
    'plus 98 prefix' => '+989121234567',
    '0098 prefix' => '00989121234567',
    '98 prefix' => '989121234567',
    'without leading zero' => '9121234567',
    'with separators' => ' 0912-123 (4567) ',
]);

it('returns input that is not a mobile number unchanged so validation can reject it', function (string $input, string $expected) {
    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with([
    'landline' => ['02112345678', '02112345678'],
    'too short' => ['0912123', '0912123'],
    'letters' => [' abc ', 'abc'],
]);

it('converts a normalized number to international format', function () {
    expect(PhoneNumber::toInternational('09121234567'))->toBe('+989121234567');
});
