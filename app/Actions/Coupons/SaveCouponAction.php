<?php

declare(strict_types=1);

namespace App\Actions\Coupons;

use App\Data\Coupons\CouponData;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class SaveCouponAction
{
    public function __construct(
        private SyncCouponTargetsAction $syncTargets,
        private SyncCouponCustomersAction $syncCustomers,
    ) {}

    /**
     * Create ($coupon null) or update a coupon with its eligible items and the customers it is
     * limited to.
     */
    public function handle(User $actor, ?Coupon $coupon, CouponData $data): Coupon
    {
        return DB::transaction(function () use ($actor, $coupon, $data): Coupon {
            $creating = $coupon === null;
            $coupon ??= new Coupon(['created_by' => $actor->id]);
            $terms = $data->terms;

            $coupon->fill([
                'code' => $data->code,
                'name' => $data->name,
                'type' => $terms->type,
                'value' => $terms->value,
                'max_discount' => $terms->maxDiscount,
                'applies_to' => $terms->appliesTo,
                'min_order_amount' => $terms->minOrderAmount,
                'exclude_discounted' => $terms->excludeDiscounted,
                'usage_limit' => $data->usageLimit,
                'per_customer_limit' => $data->perCustomerLimit,
                'first_order_only' => $data->firstOrderOnly,
                // Saved without a start, the coupon runs from now on.
                'starts_at' => $data->startsAt ?? now(),
                'ends_at' => $data->endsAt,
                'is_active' => $data->isActive,
            ]);

            $changedFields = array_values(array_diff(array_keys($coupon->getDirty()), ['created_by']));
            $coupon->updated_by = $actor->id;
            $coupon->save();

            array_push($changedFields, ...$this->syncTargets->handle($coupon, $terms));

            if ($this->syncCustomers->handle($coupon, $data->customerScope, $data->customerIds)) {
                $changedFields[] = 'customers';
            }

            if (! $creating && $changedFields === []) {
                return $coupon;
            }

            $event = $creating ? 'coupon.created' : 'coupon.updated';

            // Codes aren't personal data; chosen customers are never listed, only `customers` as a changed field.
            activity('admin')
                ->causedBy($actor)
                ->performedOn($coupon)
                ->event($event)
                ->withProperties($creating ? ['code' => $coupon->code] : ['code' => $coupon->code, 'fields' => $changedFields])
                ->log($event);

            return $coupon;
        });
    }
}
