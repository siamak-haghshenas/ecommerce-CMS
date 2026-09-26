<?php

declare(strict_types=1);

namespace App\Services\Sms\Drivers;

use App\Contracts\SmsSender;

/**
 * Keeps sent codes in memory so tests can read them.
 */
final class ArraySmsDriver implements SmsSender
{
    /**
     * @var array<string, list<string>>
     */
    private array $sentCodes = [];

    public function sendOtp(string $phoneNumber, string $code): void
    {
        $this->sentCodes[$phoneNumber][] = $code;
    }

    public function lastCodeFor(string $phoneNumber): ?string
    {
        $codes = $this->sentCodes[$phoneNumber] ?? [];

        return $codes === [] ? null : $codes[array_key_last($codes)];
    }

    public function countFor(string $phoneNumber): int
    {
        return count($this->sentCodes[$phoneNumber] ?? []);
    }
}
