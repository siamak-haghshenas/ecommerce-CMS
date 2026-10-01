<?php

declare(strict_types=1);

namespace App\Data\Admin;

final readonly class CustomerFilters
{
    /**
     * @param  'active'|'inactive'|'anonymised'|null  $status
     * @param  'completed'|'pending'|null  $registration
     */
    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
        public ?string $registration = null,
        public int $perPage = 20,
    ) {}
}
