<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Staff;

use App\Data\Admin\AddStaffMemberData;
use App\Enums\UserRole;
use App\Http\Requests\Concerns\HasAuthFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    use HasAuthFields;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone_number' => $this->phoneNumberRules(),
            // Only required when the number has no account yet (checked by the action).
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(UserRole::staffRoleValues())],
        ];
    }

    public function toData(): AddStaffMemberData
    {
        return new AddStaffMemberData(
            phoneNumber: $this->phoneNumber(),
            name: $this->filled('name') ? $this->string('name')->trim()->toString() : null,
            role: UserRole::from($this->string('role')->toString()),
        );
    }
}
