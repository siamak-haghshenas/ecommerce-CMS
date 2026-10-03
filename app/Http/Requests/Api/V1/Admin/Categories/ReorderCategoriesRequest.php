<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Categories;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderCategoriesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['present', 'nullable', 'integer', Rule::exists('categories', 'id')],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', Rule::exists('categories', 'id')],
        ];
    }

    public function parentId(): ?int
    {
        return $this->filled('parent_id') ? $this->integer('parent_id') : null;
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map(intval(...), (array) $this->input('ids')));
    }
}
