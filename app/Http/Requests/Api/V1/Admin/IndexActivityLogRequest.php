<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexActivityLogRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject_id' => ['nullable', 'integer'],
            'causer_id' => ['nullable', 'integer'],
            'event' => ['nullable', 'string', 'max:64'],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ];
    }
}
