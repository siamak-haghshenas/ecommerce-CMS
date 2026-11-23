<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Storefront\Cart;

use App\Support\Digits;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A code typed by the customer, matched like the admin saves it (uppercase, Latin digits).
 */
class ApplyCartCouponRequest extends FormRequest
{
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
        ];
    }

    public function couponCode(): string
    {
        return $this->string('code')->toString();
    }
}
