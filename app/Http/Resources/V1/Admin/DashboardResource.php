<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Data\Admin\DashboardSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property DashboardSummary $resource
 */
class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'stats' => [
                'customers_total' => $this->resource->customersTotal,
                'customers_new_this_week' => $this->resource->customersNewThisWeek,
                'customers_new_previous_week' => $this->resource->customersNewPreviousWeek,
                'pending_registrations' => $this->resource->pendingRegistrations,
                'staff_total' => $this->resource->staffTotal,
            ],
            'recent_customers' => CustomerResource::collection($this->resource->recentCustomers),
        ];
    }
}
