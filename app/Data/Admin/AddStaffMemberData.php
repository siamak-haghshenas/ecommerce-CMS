<?php

declare(strict_types=1);

namespace App\Data\Admin;

use App\Enums\UserRole;

final readonly class AddStaffMemberData
{
    public function __construct(
        public string $phoneNumber,
        public ?string $name,
        public UserRole $role,
    ) {}
}
