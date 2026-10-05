<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Categories;

use App\Enums\CategoryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One type's tree: product categories need categories.view, blog categories blog_categories.manage.
 */
class IndexCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->type()->viewPermission()->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['type' => ['nullable', Rule::enum(CategoryType::class)]];
    }

    public function type(): CategoryType
    {
        return CategoryType::tryFrom($this->string('type')->toString()) ?? CategoryType::Product;
    }
}
