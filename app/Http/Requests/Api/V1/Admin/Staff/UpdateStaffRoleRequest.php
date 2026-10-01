<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Staff;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRoleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(UserRole::staffRoleValues())],
        ];
    }

    public function role(): UserRole
    {
        return UserRole::from($this->string('role')->toString());
    }
}
