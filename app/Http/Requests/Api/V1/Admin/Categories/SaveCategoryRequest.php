<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Categories;

use App\Data\Categories\CategoryData;
use App\Enums\CategoryType;
use App\Http\Requests\Concerns\HasMediaFields;
use App\Http\Requests\Concerns\HasSlugField;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Creating and updating a category share the same fields. `type` is only read when creating: a
 * category's type never changes. Slugs are unique per type, and a parent must be of the same type.
 */
class SaveCategoryRequest extends FormRequest
{
    use HasMediaFields, HasSlugField;

    public function authorize(): bool
    {
        return $this->user()?->can($this->categoryType()->managePermission()->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeSlug();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Category|null $category */
        $category = $this->route('category');

        $type = $this->categoryType()->value;

        return [
            'type' => [$category === null ? 'nullable' : 'exclude', Rule::enum(CategoryType::class)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                ...array_filter($this->slugRules('categories', $category?->id), fn (mixed $rule): bool => ! $rule instanceof Unique),
                Rule::unique('categories', 'slug')->where('type', $type)->ignore($category?->id),
            ],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('type', $type)],
            'description' => ['nullable', 'string', 'max:5000'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'focus_keyphrase' => ['nullable', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            // The image: an image of the media library (null: no image).
            'image_id' => ['nullable', 'integer', $this->libraryImageRule()],
        ];
    }

    public function toData(): CategoryData
    {
        return new CategoryData(
            name: $this->string('name')->trim()->toString(),
            slug: $this->string('slug')->toString(),
            parentId: $this->filled('parent_id') ? $this->integer('parent_id') : null,
            description: $this->optionalText('description'),
            metaTitle: $this->optionalText('meta_title'),
            metaDescription: $this->optionalText('meta_description'),
            focusKeyphrase: $this->optionalText('focus_keyphrase'),
            isActive: $this->boolean('is_active'),
            imageId: $this->libraryImageId('image_id'),
            type: $this->categoryType(),
        );
    }

    /**
     * The category's own type when updating; the requested one (product by default) when creating.
     */
    public function categoryType(): CategoryType
    {
        /** @var Category|null $category */
        $category = $this->route('category');

        return $category->type ?? CategoryType::tryFrom($this->string('type')->toString()) ?? CategoryType::Product;
    }

    private function optionalText(string $key): ?string
    {
        $value = $this->string($key)->trim()->toString();

        return $value === '' ? null : $value;
    }
}
