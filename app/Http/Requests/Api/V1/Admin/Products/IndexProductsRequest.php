<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Products;

use App\Data\Products\ProductFilters;
use App\Enums\ProductStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'category_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'tag_id' => ['nullable', 'integer'],
            'stock' => ['nullable', Rule::in(['low', 'out'])],
            'needs_seo' => ['nullable', 'boolean'],
            'trashed' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['newest', 'name', 'updated'])],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
            'quick' => ['nullable', Rule::in(['out', 'low', 'sale', 'inactive'])],
        ];
    }

    public function toFilters(): ProductFilters
    {
        $integer = fn (string $key): ?int => $this->filled($key) ? $this->integer($key) : null;

        return new ProductFilters(
            search: $this->filled('q') ? $this->string('q')->toString() : null,
            status: $this->filled('status') ? ProductStatus::from($this->string('status')->toString()) : null,
            categoryId: $integer('category_id'),
            brandId: $integer('brand_id'),
            tagId: $integer('tag_id'),
            stock: $this->filled('stock') ? $this->string('stock')->toString() : null,
            needsSeo: $this->boolean('needs_seo'),
            trashed: $this->boolean('trashed'),
            sort: $this->string('sort', 'newest')->toString(),
            perPage: $integer('per_page') ?? 20,
            quick: $this->filled('quick') ? $this->string('quick')->toString() : null,
        );
    }
}
