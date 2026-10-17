<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Storefront\Coupons;

use App\Data\Coupons\CouponItem;
use App\Http\Requests\Concerns\HasCouponItemFields;
use App\Support\Digits;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A code typed by a customer and their cart. The code is matched like the admin saves it
 * (uppercase, Latin digits); every item must be an enabled variant of a product the storefront
 * shows.
 */
class CheckCouponRequest extends FormRequest
{
    use HasCouponItemFields;

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
            'code' => ['required', 'string', 'max:100'],
            ...$this->couponItemRules(),
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

    public function couponCode(): string
    {
        return $this->string('code')->toString();
    }

    /**
     * @return list<CouponItem>
     */
    public function cartItems(): array
    {
        return $this->couponItems();
    }
}
