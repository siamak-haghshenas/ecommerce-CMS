<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Coupons;

use App\Data\Coupons\CouponData;
use App\Enums\CouponCustomerScope;
use App\Http\Requests\Concerns\HasCouponCustomerFields;
use App\Http\Requests\Concerns\HasCouponTermsFields;
use App\Models\Coupon;
use App\Support\Digits;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Creating and updating a coupon share the same fields. The code is stored in uppercase with
 * Latin digits, so "yalda۲۰" and "YALDA20" are the same code.
 */
class SaveCouponRequest extends FormRequest
{
    use HasCouponCustomerFields, HasCouponTermsFields;

    /** The largest count a field takes. */
    private const int MAX_COUNT = 1_000_000_000;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper(Digits::toLatin(trim($this->string('code')->toString())))]);
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
            'code' => [
                'required',
                'string',
                'min:'.Coupon::CODE_MIN,
                'max:'.Coupon::CODE_MAX,
                'regex:/^[A-Z0-9]+$/',
                // Deleted coupons keep their code: an old order's code must never mean a new discount.
                Rule::unique('coupons', 'code')->ignore($this->coupon()?->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            ...$this->couponTermsRules(),
            ...$this->couponCustomerRules(),
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_COUNT],
            'per_customer_limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_COUNT],
            'first_order_only' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Checks across fields: the terms (percentage, chosen items), an end after the start and
     * chosen customers who are customers.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->checkCouponTerms($validator);

                $start = $this->date('starts_at');
                $end = $this->date('ends_at');

                if ($start !== null && $end !== null && $end->lte($start)) {
                    $validator->errors()->add('ends_at', __('admin.coupons.errors.end_before_start'));
                }

                $this->checkCouponCustomers($validator);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => __('validation.attributes.coupon_code'),
            'value' => __('validation.attributes.discount_value'),
            'is_active' => __('validation.attributes.coupon_active'),
        ];
    }

    public function toData(): CouponData
    {
        $scope = $this->couponCustomerScope();
        $optional = fn (string $key): ?int => $this->filled($key) ? $this->integer($key) : null;
        $date = fn (string $key): ?CarbonImmutable => $this->filled($key) ? CarbonImmutable::parse($this->string($key)->toString()) : null;

        return new CouponData(
            code: $this->string('code')->toString(),
            name: $this->string('name')->trim()->toString(),
            terms: $this->couponTerms(),
            customerScope: $scope,
            customerIds: $scope === CouponCustomerScope::Selected ? $this->couponCustomerIds() : [],
            usageLimit: $optional('usage_limit'),
            perCustomerLimit: $optional('per_customer_limit'),
            firstOrderOnly: $this->boolean('first_order_only'),
            startsAt: $date('starts_at'),
            endsAt: $date('ends_at'),
            isActive: $this->boolean('is_active'),
        );
    }

    private function coupon(): ?Coupon
    {
        $coupon = $this->route('coupon');

        return $coupon instanceof Coupon ? $coupon : null;
    }
}
