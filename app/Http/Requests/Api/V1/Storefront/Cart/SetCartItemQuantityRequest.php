<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Storefront\Cart;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The new quantity of a cart line (to take it out, delete the line).
 */
class SetCartItemQuantityRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function quantity(): int
    {
        return $this->integer('quantity');
    }
}
