<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Coupons;

use App\Data\Coupons\CouponFilters;
use App\Models\Coupon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCouponsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Coupon::STATES)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function toFilters(): CouponFilters
    {
        return new CouponFilters(
            search: $this->filled('q') ? $this->string('q')->trim()->toString() : null,
            state: $this->filled('status') ? $this->string('status')->toString() : null,
            perPage: $this->filled('per_page') ? $this->integer('per_page') : 20,
        );
    }
}
