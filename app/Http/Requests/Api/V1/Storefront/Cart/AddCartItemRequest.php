<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Storefront\Cart;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A variant to put in the cart and how many units (1 when left out). Whether it is on sale and
 * fits the line's limit is checked by AddCartItemAction.
 */
class AddCartItemRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'variant_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function variantId(): int
    {
        return $this->integer('variant_id');
    }

    public function quantity(): int
    {
        return $this->integer('quantity', 1);
    }
}
