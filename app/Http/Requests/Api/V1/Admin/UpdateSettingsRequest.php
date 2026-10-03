<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\PriceDisplayCurrency;
use App\Support\Settings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Any subset of the store settings.
 */
class UpdateSettingsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            Settings::PRICE_DISPLAY_CURRENCY => ['sometimes', Rule::enum(PriceDisplayCurrency::class)],
            Settings::LOW_STOCK_THRESHOLD => ['sometimes', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
