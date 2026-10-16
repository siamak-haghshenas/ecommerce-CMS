<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer a coupon is limited to.
 *
 * @mixin User
 */
class CouponCustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone_number' => $this->phone_number,
            // Counted from orders once they exist.
            'used_count' => 0,
        ];
    }
}
