<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Categories;

use App\Enums\CategoryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The complete child order of one parent of one type's tree (`type`: product by default).
 */
class ReorderCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->type()->managePermission()->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ofType = fn () => Rule::exists('categories', 'id')->where('type', $this->type()->value);

        return [
            'type' => ['nullable', Rule::enum(CategoryType::class)],
            'parent_id' => ['present', 'nullable', 'integer', $ofType()],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', $ofType()],
        ];
    }

    public function type(): CategoryType
    {
        return CategoryType::tryFrom($this->string('type')->toString()) ?? CategoryType::Product;
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
