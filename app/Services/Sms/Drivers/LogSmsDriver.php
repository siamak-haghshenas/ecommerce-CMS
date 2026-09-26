<?php

declare(strict_types=1);

namespace App\Services\Sms\Drivers;

use App\Contracts\SmsSender;
use Psr\Log\LoggerInterface;

/**
 * Writes messages to the log instead of sending them. For local development only.
 */
final readonly class LogSmsDriver implements SmsSender
{
    public function __construct(private LoggerInterface $logger) {}

    public function sendOtp(string $phoneNumber, string $code): void
    {
        $this->logger->info("[SMS] OTP for {$phoneNumber}: {$code}");
    }
}
