<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\CouponCustomerScope;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * "Who can use it" of the coupon save: `customer_scope` and, for `selected`, `customer_ids`
 * (at least one, each a customer account that isn't anonymised).
 */
trait HasCouponCustomerFields
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function couponCustomerRules(): array
    {
        return [
            'customer_scope' => ['required', Rule::enum(CouponCustomerScope::class)],
            'customer_ids' => ['present', 'array', 'required_if:customer_scope,'.CouponCustomerScope::Selected->value, 'max:'.Coupon::MAX_CUSTOMERS],
            'customer_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * Call from after(): every chosen id must be a customer (staff and anonymised accounts can't
     * be chosen; deactivated customers may stay, they can't log in anyway).
     */
    protected function checkCouponCustomers(Validator $validator): void
    {
        if ($this->couponCustomerScope() !== CouponCustomerScope::Selected) {
            return;
        }

        $ids = $this->couponCustomerIds();
        $allowed = User::query()->customers()->whereNull('anonymised_at')->whereKey($ids)->pluck('id')->all();

        foreach ($ids as $i => $id) {
            if (! in_array($id, $allowed, true)) {
                $validator->errors()->add("customer_ids.{$i}", __('admin.coupons.errors.not_a_customer'));
            }
        }
    }

    protected function couponCustomerScope(): CouponCustomerScope
    {
        return CouponCustomerScope::from($this->string('customer_scope')->toString());
    }

    /**
     * @return list<int>
     */
    protected function couponCustomerIds(): array
    {
        return array_values(array_map(intval(...), (array) $this->input('customer_ids', [])));
    }
}
