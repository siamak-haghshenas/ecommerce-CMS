<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Brands;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderBrandsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', Rule::exists('brands', 'id')],
        ];
    }

    /**
     * The complete new order, first brand first.
     *
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map(intval(...), (array) $this->input('ids')));
    }
}
