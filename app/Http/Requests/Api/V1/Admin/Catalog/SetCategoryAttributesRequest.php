<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Catalog;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetCategoryAttributesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // An empty list removes all of the category's own attributes.
            'ids' => ['present', 'array', 'max:200'],
            'ids.*' => ['integer', 'distinct', Rule::exists('attributes', 'id')],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map(intval(...), (array) $this->input('ids', [])));
    }
}
