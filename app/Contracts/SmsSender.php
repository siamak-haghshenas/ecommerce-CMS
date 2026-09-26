<?php

declare(strict_types=1);

namespace App\Contracts;

interface SmsSender
{
    /**
     * Send a one-time password to the given normalized phone number (09xxxxxxxxx).
     */
    public function sendOtp(string $phoneNumber, string $code): void;
}
