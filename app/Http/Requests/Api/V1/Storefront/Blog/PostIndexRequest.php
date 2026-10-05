<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Storefront\Blog;

use App\Data\Posts\StorefrontPostFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PostIndexRequest extends FormRequest
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
            'category' => ['nullable', 'string', 'max:120'],
            'tag' => ['nullable', 'string', 'max:120'],
            'author' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,48'],
        ];
    }

    public function toFilters(): StorefrontPostFilters
    {
        $text = fn (string $key): ?string => $this->filled($key) ? $this->string($key)->trim()->toString() : null;

        return new StorefrontPostFilters(
            search: $text('q'),
            category: $text('category'),
            tag: $text('tag'),
            author: $text('author'),
            perPage: $this->filled('per_page') ? $this->integer('per_page') : 12,
        );
    }
}
