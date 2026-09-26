<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Auth;

use App\Http\Requests\Concerns\HasAuthFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordLoginRequest extends FormRequest
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
            'password' => ['required', 'string'],
        ];
    }
}
