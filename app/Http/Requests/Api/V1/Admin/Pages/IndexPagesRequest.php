<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Pages;

use App\Data\Pages\PageFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPagesRequest extends FormRequest
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
            'needs_seo' => ['nullable', 'boolean'],
            'trashed' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['updated', 'newest', 'title'])],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ];
    }

    public function toFilters(): PageFilters
    {
        return new PageFilters(
            search: $this->filled('q') ? $this->string('q')->toString() : null,
            state: $this->filled('status') ? $this->string('status')->toString() : null,
            needsSeo: $this->boolean('needs_seo'),
            trashed: $this->boolean('trashed'),
            sort: $this->string('sort', 'updated')->toString(),
            perPage: $this->filled('per_page') ? $this->integer('per_page') : 20,
        );
    }
}
