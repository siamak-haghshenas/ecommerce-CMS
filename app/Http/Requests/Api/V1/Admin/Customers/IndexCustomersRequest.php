<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Customers;

use App\Data\Admin\CustomerFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexCustomersRequest extends FormRequest
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
            'status' => ['nullable', 'in:active,inactive,anonymised'],
            'registration' => ['nullable', 'in:completed,pending'],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ];
    }

    public function toFilters(): CustomerFilters
    {
        /** @var 'active'|'inactive'|'anonymised'|null $status */
        $status = $this->validated('status');
        /** @var 'completed'|'pending'|null $registration */
        $registration = $this->validated('registration');

        return new CustomerFilters(
            search: $this->filled('q') ? $this->string('q')->toString() : null,
            status: $status,
            registration: $registration,
            perPage: $this->integer('per_page', 20),
        );
    }
}
