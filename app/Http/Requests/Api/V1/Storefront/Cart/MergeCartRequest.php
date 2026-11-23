<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Storefront\Cart;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The guest cart's token the storefront kept from before the login.
 */
class MergeCartRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cart_token' => ['required', 'string', 'max:100'],
        ];
    }

    public function cartToken(): string
    {
        return $this->string('cart_token')->toString();
    }
}
