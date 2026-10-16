<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Coupons;

use App\Data\Coupons\CouponItem;
use App\Data\Coupons\CouponTerms;
use App\Http\Requests\Concerns\HasCouponItemFields;
use App\Http\Requests\Concerns\HasCouponTermsFields;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The editor's sample order: `coupon` (the save payload as it is, unsaved; only its terms are
 * read, so the code, customers and dates may be anything) and sample `items` (any variant that
 * isn't deleted, drafts included).
 */
class SimulateCouponRequest extends FormRequest
{
    use HasCouponItemFields, HasCouponTermsFields;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'coupon' => ['required', 'array'],
            ...$this->couponTermsRules('coupon.'),
            ...$this->couponItemRules([Rule::exists('product_variants', 'id')->whereNull('deleted_at')]),
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
                    $this->checkCouponTerms($validator, 'coupon.');
                }
            },
        ];
    }

    public function terms(): CouponTerms
    {
        return $this->couponTerms('coupon.');
    }

    /**
     * @return list<CouponItem>
     */
    public function cartItems(): array
    {
        return $this->couponItems();
    }
}
