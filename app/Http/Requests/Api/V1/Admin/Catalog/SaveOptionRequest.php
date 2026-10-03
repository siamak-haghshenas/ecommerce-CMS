<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Catalog;

use App\Data\Catalog\OptionData;
use App\Data\Catalog\ValueData;
use App\Enums\OptionDisplayType;
use App\Http\Requests\Concerns\HasSlugField;
use App\Models\Option;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating and updating an option share the same fields. Values are sent in display order;
 * an existing value keeps its id. "color" options need a color per value.
 */
class SaveOptionRequest extends FormRequest
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
        $option = $this->route('option');
        $optionId = $option instanceof Option ? $option->id : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => $this->slugRules('options', $optionId),
            'display_type' => ['required', Rule::enum(OptionDisplayType::class)],
            'values' => ['required', 'array', 'min:1', 'max:200'],
            'values.*.id' => ['nullable', 'integer', Rule::exists('option_values', 'id')->where('option_id', $optionId ?? 0)],
            'values.*.value' => ['required', 'string', 'max:120'],
            'values.*.slug' => ['required', 'string', 'max:120', 'regex:'.self::SLUG_PATTERN, 'distinct'],
            'values.*.color_hex' => ['nullable', 'required_if:display_type,color', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    public function toData(): OptionData
    {
        $displayType = OptionDisplayType::from($this->string('display_type')->toString());

        return new OptionData(
            name: $this->string('name')->trim()->toString(),
            slug: $this->string('slug')->toString(),
            displayType: $displayType,
            values: array_values(array_map(fn (array $value): ValueData => new ValueData(
                id: isset($value['id']) ? (int) $value['id'] : null,
                value: trim((string) $value['value']),
                slug: (string) $value['slug'],
                // Only color options keep colors.
                colorHex: $displayType === OptionDisplayType::Color ? strtolower((string) $value['color_hex']) : null,
            ), (array) $this->input('values', []))),
        );
    }
}
