<?php

declare(strict_types=1);

namespace App\Data\Admin;

use App\Enums\UserRole;

final readonly class StaffFilters
{
    /**
     * @param  'active'|'inactive'|null  $status
     */
    public function __construct(
        public ?string $search = null,
        public ?UserRole $role = null,
        public ?string $status = null,
        public int $perPage = 20,
    ) {}
}
