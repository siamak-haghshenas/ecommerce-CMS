<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\SaleEvents;

use App\Actions\Products\MatchingProductsAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaleEventVariantsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1', 'max:'.MatchingProductsAction::LIMIT],
            'product_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * The chosen products, in the order they were chosen.
     *
     * @return list<int>
     */
    public function productIds(): array
    {
        return array_values(array_map(intval(...), (array) $this->input('product_ids')));
    }
}
