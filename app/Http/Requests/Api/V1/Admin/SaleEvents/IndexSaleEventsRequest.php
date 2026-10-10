<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\SaleEvents;

use App\Data\SaleEvents\SaleEventFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSaleEventsRequest extends FormRequest
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
        ];
    }

    public function toFilters(): SaleEventFilters
    {
        return new SaleEventFilters(
            search: $this->filled('q') ? $this->string('q')->trim()->toString() : null,
            state: $this->filled('status') ? $this->string('status')->toString() : null,
        );
    }
}
