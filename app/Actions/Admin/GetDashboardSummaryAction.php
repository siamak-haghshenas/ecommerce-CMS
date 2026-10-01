<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Data\Admin\DashboardSummary;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class GetDashboardSummaryAction
{
    public const int RECENT_CUSTOMERS_LIMIT = 5;

    /**
     * Customer and staff figures for the admin dashboard. A customer counts from the
     * moment their phone number is verified; anonymised customers are left out.
     */
    public function handle(): DashboardSummary
    {
        $now = now()->toImmutable();
        $weekAgo = $now->minus(days: 7);

        return new DashboardSummary(
            customersTotal: $this->customers()->count(),
            customersNewThisWeek: $this->customers()->where('phone_verified_at', '>=', $weekAgo)->count(),
            customersNewPreviousWeek: $this->customers()
                ->where('phone_verified_at', '>=', $weekAgo->minus(days: 7))
                ->where('phone_verified_at', '<', $weekAgo)
                ->count(),
            pendingRegistrations: User::query()->customers()->whereNull('phone_verified_at')->count(),
            staffTotal: User::query()->staff()->count(),
            recentCustomers: $this->customers()
                ->latest('phone_verified_at')
                ->limit(self::RECENT_CUSTOMERS_LIMIT)
                ->get(),
        );
    }

    /**
     * @return Builder<User>
     */
    private function customers(): Builder
    {
        return User::query()->customers()->whereNotNull('phone_verified_at')->whereNull('anonymised_at');
    }
}
