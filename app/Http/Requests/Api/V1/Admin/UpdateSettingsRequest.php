<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\PriceDisplayCurrency;
use App\Support\Settings;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            // e.g. https://shop.example/preview?token={token}; null removes it.
            Settings::STOREFRONT_PREVIEW_URL => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $url = $this->input(Settings::STOREFRONT_PREVIEW_URL);

                if (! is_string($url) || $validator->errors()->has(Settings::STOREFRONT_PREVIEW_URL)) {
                    return;
                }

                // An http/https address once the placeholder is filled in, and the placeholder must be there.
                $filled = str_replace('{token}', 'token', $url);
                $valid = str_contains($url, '{token}')
                    && preg_match('#^https?://#i', $url) === 1
                    && filter_var($filled, FILTER_VALIDATE_URL) !== false;

                if (! $valid) {
                    $validator->errors()->add(Settings::STOREFRONT_PREVIEW_URL, __('admin.errors.preview_url_invalid'));
                }
            },
        ];
    }
}
