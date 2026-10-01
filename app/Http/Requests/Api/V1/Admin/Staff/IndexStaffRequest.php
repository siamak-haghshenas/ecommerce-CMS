<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Staff;

use App\Data\Admin\StaffFilters;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexStaffRequest extends FormRequest
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
            'role' => ['nullable', Rule::in(UserRole::staffRoleValues())],
            'status' => ['nullable', 'in:active,inactive'],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ];
    }

    public function toFilters(): StaffFilters
    {
        /** @var 'active'|'inactive'|null $status */
        $status = $this->validated('status');

        return new StaffFilters(
            search: $this->filled('q') ? $this->string('q')->toString() : null,
            role: $this->filled('role') ? UserRole::from($this->string('role')->toString()) : null,
            status: $status,
            perPage: $this->integer('per_page', 20),
        );
    }
}
