<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Customers;

use App\Data\Admin\UpdateCustomerData;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $customer */
        $customer = $this->route('customer');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($customer->id)],
            'locale' => ['required', Rule::in(config('app.supported_locales'))],
        ];
    }

    public function toData(): UpdateCustomerData
    {
        return new UpdateCustomerData(
            name: $this->string('name')->trim()->toString(),
            email: $this->filled('email') ? $this->string('email')->lower()->toString() : null,
            locale: $this->string('locale')->toString(),
        );
    }
}
