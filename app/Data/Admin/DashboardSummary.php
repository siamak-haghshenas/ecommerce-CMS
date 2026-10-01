<?php

declare(strict_types=1);

namespace App\Data\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final readonly class DashboardSummary
{
    /**
     * @param  Collection<int, User>  $recentCustomers
     */
    public function __construct(
        public int $customersTotal,
        public int $customersNewThisWeek,
        public int $customersNewPreviousWeek,
        public int $pendingRegistrations,
        public int $staffTotal,
        public Collection $recentCustomers,
    ) {}
}
