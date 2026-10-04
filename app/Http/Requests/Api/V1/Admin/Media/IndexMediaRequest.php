<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Media;

use App\Data\Media\MediaFilters;
use App\Enums\MediaType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMediaRequest extends FormRequest
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
            'type' => ['nullable', Rule::enum(MediaType::class)],
            // A folder id, or "none" for files in no folder.
            'folder' => ['nullable', 'regex:/^(none|\d+)$/'],
            'used' => ['nullable', 'boolean'],
            'trashed' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name', 'size'])],
            'per_page' => ['nullable', 'integer', 'between:10,100'],
        ];
    }

    public function toFilters(): MediaFilters
    {
        $folder = $this->string('folder')->toString();

        return new MediaFilters(
            search: $this->filled('q') ? $this->string('q')->toString() : null,
            type: $this->filled('type') ? MediaType::from($this->string('type')->toString()) : null,
            folder: $folder === '' ? null : ($folder === 'none' ? 'none' : (int) $folder),
            used: $this->filled('used') ? $this->boolean('used') : null,
            trashed: $this->boolean('trashed'),
            sort: $this->string('sort', 'newest')->toString(),
            perPage: $this->filled('per_page') ? $this->integer('per_page') : 40,
        );
    }
}
