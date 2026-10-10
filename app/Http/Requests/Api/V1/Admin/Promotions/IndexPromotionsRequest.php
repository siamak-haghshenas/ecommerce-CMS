<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Promotions;

use App\Data\Promotions\PromotionFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPromotionsRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(['active', 'scheduled', 'expired', 'disabled'])],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ];
    }

    public function toFilters(): PromotionFilters
    {
        return new PromotionFilters(
            search: $this->filled('q') ? $this->string('q')->trim()->toString() : null,
            state: $this->filled('status') ? $this->string('status')->toString() : null,
            perPage: $this->filled('per_page') ? $this->integer('per_page') : 20,
        );
    }
}
