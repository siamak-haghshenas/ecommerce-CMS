<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Catalog;

use App\Data\Catalog\AttributeData;
use App\Data\Catalog\ValueData;
use App\Enums\AttributeType;
use App\Http\Requests\Concerns\HasSlugField;
use App\Models\Attribute;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating and updating an attribute share the same fields. Values are sent in display order;
 * an existing value keeps its id.
 */
class SaveAttributeRequest extends FormRequest
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
        $attribute = $this->route('attribute');
        $attributeId = $attribute instanceof Attribute ? $attribute->id : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => $this->slugRules('attributes', $attributeId),
            'type' => ['required', Rule::enum(AttributeType::class)],
            'unit' => ['nullable', 'string', 'max:20'],
            'is_filterable' => ['required', 'boolean'],
            // A "select" attribute needs at least one value; other types ignore the list.
            'values' => ['array', 'required_if:type,select', 'max:200'],
            'values.*.id' => ['nullable', 'integer', Rule::exists('attribute_values', 'id')->where('attribute_id', $attributeId ?? 0)],
            'values.*.value' => ['required', 'string', 'max:120'],
            'values.*.slug' => ['required', 'string', 'max:120', 'regex:'.self::SLUG_PATTERN, 'distinct'],
        ];
    }

    public function toData(): AttributeData
    {
        $type = AttributeType::from($this->string('type')->toString());
        $unit = $this->string('unit')->trim()->toString();

        return new AttributeData(
            name: $this->string('name')->trim()->toString(),
            slug: $this->string('slug')->toString(),
            type: $type,
            unit: $type === AttributeType::Number && $unit !== '' ? $unit : null,
            isFilterable: $this->boolean('is_filterable'),
            values: $type->hasValues() ? $this->valueData() : [],
        );
    }

    /**
     * @return list<ValueData>
     */
    private function valueData(): array
    {
        return array_values(array_map(fn (array $value): ValueData => new ValueData(
            id: isset($value['id']) ? (int) $value['id'] : null,
            value: trim((string) $value['value']),
            slug: (string) $value['slug'],
        ), (array) $this->input('values', [])));
    }
}
