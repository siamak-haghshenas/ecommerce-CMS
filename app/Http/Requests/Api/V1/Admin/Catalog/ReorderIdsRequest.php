<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Catalog;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A complete list order: every id of the list, first item first. ReorderListAction checks
 * that the ids are exactly the current items.
 */
class ReorderIdsRequest extends FormRequest
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
            'ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map(intval(...), (array) $this->input('ids')));
    }
}
