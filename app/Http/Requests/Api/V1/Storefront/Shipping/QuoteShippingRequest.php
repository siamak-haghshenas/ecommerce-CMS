<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Storefront\Shipping;

use App\Data\Coupons\CouponItem;
use App\Http\Requests\Concerns\HasCouponItemFields;
use App\Support\Digits;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The address's city, the cart (enabled variants of products the storefront shows) and an
 * optional coupon code (checked like `coupons/check`).
 */
class QuoteShippingRequest extends FormRequest
{
    use HasCouponItemFields;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('coupon'))) {
            $this->merge(['coupon' => mb_strtoupper(Digits::toLatin(trim($this->string('coupon')->toString()))) ?: null]);
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
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')],
            ...$this->couponItemRules(),
            'coupon' => ['nullable', 'string', 'max:100'],
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
                    $this->checkItemsOnSale($validator);
                }
            },
        ];
    }

    public function cityId(): int
    {
        return $this->integer('city_id');
    }

    /**
     * @return list<CouponItem>
     */
    public function cartItems(): array
    {
        return $this->couponItems();
    }

    public function couponCode(): ?string
    {
        return $this->filled('coupon') ? $this->string('coupon')->toString() : null;
    }
}
