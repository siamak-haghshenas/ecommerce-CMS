<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Storefront\Comments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CommentsIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ];
    }

    public function perPage(): int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : 20;
    }
}
