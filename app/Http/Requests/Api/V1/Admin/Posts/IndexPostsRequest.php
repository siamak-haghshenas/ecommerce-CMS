<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Posts;

use App\Data\Posts\PostFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPostsRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(['draft', 'published', 'scheduled'])],
            'category_id' => ['nullable', 'integer'],
            'tag_id' => ['nullable', 'integer'],
            'author_id' => ['nullable', 'integer'],
            'needs_seo' => ['nullable', 'boolean'],
            'trashed' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['updated', 'newest', 'title'])],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ];
    }

    public function toFilters(): PostFilters
    {
        $integer = fn (string $key): ?int => $this->filled($key) ? $this->integer($key) : null;

        return new PostFilters(
            search: $this->filled('q') ? $this->string('q')->toString() : null,
            state: $this->filled('status') ? $this->string('status')->toString() : null,
            categoryId: $integer('category_id'),
            tagId: $integer('tag_id'),
            authorId: $integer('author_id'),
            needsSeo: $this->boolean('needs_seo'),
            trashed: $this->boolean('trashed'),
            sort: $this->string('sort', 'updated')->toString(),
            perPage: $integer('per_page') ?? 20,
        );
    }
}
