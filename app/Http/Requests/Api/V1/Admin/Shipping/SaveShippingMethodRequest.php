<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Shipping;

use App\Data\Shipping\ShippingMethodData;
use App\Http\Requests\Concerns\HasShippingMethodFields;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * One save for the whole method: its fields with its ranges (weight, volume) or cities (courier).
 */
class SaveShippingMethodRequest extends FormRequest
{
    use HasShippingMethodFields;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->shippingMethodRules();
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty()) {
                    $this->checkShippingMethod($validator);
                }
            },
        ];
    }

    public function toData(): ShippingMethodData
    {
        return $this->shippingMethodData();
    }
}
