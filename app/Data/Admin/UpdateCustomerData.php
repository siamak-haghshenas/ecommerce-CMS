<?php

declare(strict_types=1);

namespace App\Data\Admin;

final readonly class UpdateCustomerData
{
    public function __construct(
        public string $name,
        public ?string $email,
        public string $locale,
    ) {}
}
