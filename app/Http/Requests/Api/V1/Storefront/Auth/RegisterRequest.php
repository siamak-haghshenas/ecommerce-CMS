<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Storefront\Auth;

use App\Data\Auth\RegisterCustomerData;
use App\Http\Requests\Concerns\HasAuthFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => [
                ...$this->phoneNumberRules(),
                Rule::unique('users', 'phone_number')->whereNotNull('phone_verified_at'),
            ],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                // An unfinished registration for the same phone may keep its own email.
                Rule::unique('users', 'email')->where(
                    fn ($query) => $query->where('phone_number', '!=', $this->phoneNumber()),
                ),
            ],
            'password' => $this->newPasswordRules(),
        ];
    }

    public function toData(): RegisterCustomerData
    {
        return new RegisterCustomerData(
            name: $this->string('name')->trim()->toString(),
            phoneNumber: $this->phoneNumber(),
            password: $this->string('password')->toString(),
            email: $this->filled('email') ? $this->string('email')->lower()->toString() : null,
            locale: App::getLocale(),
        );
    }
}
