<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Brands;

use App\Data\Brands\BrandData;
use App\Http\Requests\Concerns\HasSlugField;
use App\Models\Brand;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating and updating a brand share the same fields.
 */
class SaveBrandRequest extends FormRequest
{
    use HasSlugField;

    protected function prepareForValidation(): void
    {
        $this->normalizeSlug();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Brand|null $brand */
        $brand = $this->route('brand');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('brands', 'name')->ignore($brand?->id)],
            'persian_name' => ['nullable', 'string', 'max:120', Rule::unique('brands', 'persian_name')->ignore($brand?->id)],
            'slug' => $this->slugRules('brands', $brand?->id),
            'description' => ['nullable', 'string', 'max:5000'],
            'website' => ['nullable', 'string', 'max:255', 'url:http,https'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'focus_keyphrase' => ['nullable', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function toData(): BrandData
    {
        return new BrandData(
            name: $this->string('name')->trim()->toString(),
            persianName: $this->optionalText('persian_name'),
            slug: $this->string('slug')->toString(),
            description: $this->optionalText('description'),
            website: $this->optionalText('website'),
            metaTitle: $this->optionalText('meta_title'),
            metaDescription: $this->optionalText('meta_description'),
            focusKeyphrase: $this->optionalText('focus_keyphrase'),
            isActive: $this->boolean('is_active'),
        );
    }

    private function optionalText(string $key): ?string
    {
        $value = $this->string($key)->trim()->toString();

        return $value === '' ? null : $value;
    }
}
