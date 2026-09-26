<?php

declare(strict_types=1);

namespace App\Data\Auth;

final readonly class RegisterCustomerData
{
    public function __construct(
        public string $name,
        public string $phoneNumber,
        public string $password,
        public ?string $email,
        public string $locale,
    ) {}
}
