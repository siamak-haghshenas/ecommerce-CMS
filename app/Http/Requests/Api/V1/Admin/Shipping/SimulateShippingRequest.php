<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Shipping;

use App\Data\Coupons\CouponItem;
use App\Data\Shipping\ShippingTerms;
use App\Http\Requests\Concerns\HasCouponItemFields;
use App\Http\Requests\Concerns\HasShippingMethodFields;
use App\Models\Coupon;
use App\Support\Digits;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The editor's test box: `method` (the save payload as it is, unsaved; priced even while
 * switched off), sample `items` (any variant that isn't deleted), an optional `city_id` (needed
 * by couriers) and an optional `coupon_code` (applied whatever its dates, limits and customers).
 */
class SimulateShippingRequest extends FormRequest
{
    use HasCouponItemFields, HasShippingMethodFields;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('coupon_code'))) {
            $this->merge(['coupon_code' => mb_strtoupper(Digits::toLatin(trim($this->string('coupon_code')->toString()))) ?: null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', 'array'],
            ...$this->shippingMethodRules('method.'),
            ...$this->couponItemRules([Rule::exists('product_variants', 'id')->whereNull('deleted_at')]),
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'coupon_code' => ['nullable', 'string', Rule::exists('coupons', 'code')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty()) {
                    $this->checkShippingMethod($validator, 'method.');
                }
            },
        ];
    }

    public function terms(): ShippingTerms
    {
        return $this->shippingTerms('method.', forceActive: true);
    }

    /**
     * @return list<CouponItem>
     */
    public function cartItems(): array
    {
        return $this->couponItems();
    }

    public function cityId(): ?int
    {
        return $this->filled('city_id') ? $this->integer('city_id') : null;
    }

    public function coupon(): ?Coupon
    {
        return $this->filled('coupon_code')
            ? Coupon::query()->where('code', $this->string('coupon_code')->toString())->first()
            : null;
    }
}
