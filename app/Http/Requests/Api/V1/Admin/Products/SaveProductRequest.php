<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Products;

use App\Data\Products\ProductData;
use App\Data\Products\SpecificationData;
use App\Data\Products\VariantData;
use App\Enums\AttributeType;
use App\Enums\CategoryType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Http\Requests\Concerns\HasMediaFields;
use App\Http\Requests\Concerns\HasSlugField;
use App\Models\Attribute;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CategoryAttributes;
use App\Support\RichText;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The whole product form: fields, categories, tags, options, variants (in order) and
 * specifications. Creating and updating share it. Amounts are whole Rials.
 */
class SaveProductRequest extends FormRequest
{
    use HasMediaFields, HasSlugField;

    private const int MAX_AMOUNT = 1_000_000_000_000_000;

    private const int MAX_IMAGES = 30;

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
        $product = $this->product();

        return [
            'type' => ['required', Rule::enum(ProductType::class)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => $this->slugRules('products', $product?->id),
            // Rich text (bold, italic, links, lists); at most 1000 characters of text (checked in after()).
            'short_description' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:200000'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'published_at' => ['nullable', 'date'],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            // A published product must be in at least one category.
            'category_ids' => ['array', 'max:20', 'required_if:status,published'],
            // Product categories only (blog categories share the table).
            'category_ids.*' => ['integer', 'distinct', Rule::exists('categories', 'id')->where('type', CategoryType::Product->value)],
            'primary_category_id' => ['nullable', 'integer'],
            'tags' => ['array', 'max:30'],
            'tags.*' => ['string', 'max:60'],
            'track_stock' => ['required', 'boolean'],
            'allow_backorders' => ['required', 'boolean'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'weight_grams' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'length_mm' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'width_mm' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'height_mm' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'focus_keyphrase' => ['nullable', 'string', 'max:120'],
            'option_ids' => ['array', 'max:3', 'required_if:type,variable'],
            'option_ids.*' => ['integer', 'distinct', Rule::exists('options', 'id')],
            'variants' => ['required', 'array', 'min:1', 'max:300'],
            'variants.*.id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $product->id ?? 0)],
            'variants.*.sku' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\/-]+$/', 'distinct:ignore_case'],
            'variants.*.price' => ['required', 'integer', 'min:0', 'max:'.self::MAX_AMOUNT],
            'variants.*.sale_price' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_AMOUNT],
            'variants.*.sale_starts_at' => ['nullable', 'date'],
            'variants.*.sale_ends_at' => ['nullable', 'date'],
            'variants.*.is_enabled' => ['required', 'boolean'],
            // A variant's picture: one of the gallery's images.
            'variants.*.image_id' => ['nullable', 'integer'],
            'variants.*.option_value_ids' => ['array'],
            // Not "distinct": variants share values. checkVariants() needs one value per option.
            'variants.*.option_value_ids.*' => ['integer'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:-1000000', 'max:10000000'],
            'variants.*.stock_quantity_was' => ['nullable', 'integer'],
            // The gallery: media library images in order; the first is the main image.
            'image_ids' => ['array', 'max:'.self::MAX_IMAGES],
            'image_ids.*' => ['integer', 'distinct', $this->libraryImageRule()],
            'specifications' => ['array', 'max:200'],
            'specifications.*.attribute_id' => ['required', 'integer', 'distinct', Rule::exists('attributes', 'id')],
            'specifications.*.value_ids' => ['array'],
            'specifications.*.value_ids.*' => ['integer', 'distinct'],
            'specifications.*.text' => ['nullable', 'string', 'max:255'],
            'specifications.*.number' => ['nullable', 'numeric', 'between:-999999999,999999999'],
            'specifications.*.boolean' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Checks across fields and against the database, once the fields themselves are valid.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->checkVariants($validator);
            $this->checkCategories($validator);
            $this->checkSpecifications($validator);

            if (mb_strlen(RichText::toPlainText((string) $this->input('short_description'))) > 1000) {
                $validator->errors()->add('short_description', __('validation.max.string', ['attribute' => __('validation.attributes.short_description'), 'max' => 1000]));
            }
        }];
    }

    public function product(): ?Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : null;
    }

    public function toData(): ProductData
    {
        $type = ProductType::from($this->string('type')->toString());
        $categoryIds = $this->ints('category_ids');
        $integer = fn (string $key): ?int => $this->filled($key) ? $this->integer($key) : null;
        $text = fn (string $key): ?string => $this->filled($key) ? $this->string($key)->trim()->toString() : null;

        return new ProductData(
            type: $type,
            name: $this->string('name')->trim()->toString(),
            slug: $this->string('slug')->toString(),
            shortDescription: RichText::sanitize($this->input('short_description'), full: false),
            description: RichText::sanitize($this->input('description')),
            status: ProductStatus::from($this->string('status')->toString()),
            publishedAt: $this->filled('published_at') ? CarbonImmutable::parse($this->string('published_at')->toString()) : null,
            brandId: $integer('brand_id'),
            categoryIds: $categoryIds,
            // The first category is primary unless another one is chosen.
            primaryCategoryId: $integer('primary_category_id') ?? ($categoryIds[0] ?? null),
            tagNames: array_values(array_filter(array_map(
                fn (mixed $name): string => trim(preg_replace('/\s+/u', ' ', (string) $name) ?? ''),
                (array) $this->input('tags', []),
            ))),
            trackStock: $this->boolean('track_stock'),
            allowBackorders: $this->boolean('allow_backorders'),
            lowStockThreshold: $integer('low_stock_threshold'),
            weightGrams: $integer('weight_grams'),
            lengthMm: $integer('length_mm'),
            widthMm: $integer('width_mm'),
            heightMm: $integer('height_mm'),
            metaTitle: $text('meta_title'),
            metaDescription: $text('meta_description'),
            focusKeyphrase: $text('focus_keyphrase'),
            optionIds: $type === ProductType::Variable ? $this->ints('option_ids') : [],
            variants: array_map(fn (array $variant): VariantData => new VariantData(
                id: isset($variant['id']) ? (int) $variant['id'] : null,
                sku: trim((string) $variant['sku']),
                price: (int) $variant['price'],
                salePrice: isset($variant['sale_price']) ? (int) $variant['sale_price'] : null,
                saleStartsAt: empty($variant['sale_starts_at']) ? null : CarbonImmutable::parse($variant['sale_starts_at']),
                saleEndsAt: empty($variant['sale_ends_at']) ? null : CarbonImmutable::parse($variant['sale_ends_at']),
                isEnabled: (bool) $variant['is_enabled'],
                imageId: isset($variant['image_id']) ? (int) $variant['image_id'] : null,
                optionValueIds: $type === ProductType::Variable ? array_values(array_map(intval(...), $variant['option_value_ids'] ?? [])) : [],
                stockQuantity: isset($variant['stock_quantity']) ? (int) $variant['stock_quantity'] : null,
                stockQuantityWas: isset($variant['stock_quantity_was']) ? (int) $variant['stock_quantity_was'] : null,
            ), array_values((array) $this->input('variants'))),
            specifications: $this->specificationData(),
            imageIds: $this->ints('image_ids'),
        );
    }

    private function checkVariants(Validator $validator): void
    {
        $type = ProductType::from($this->string('type')->toString());
        $variants = array_values((array) $this->input('variants'));

        if ($type === ProductType::Simple && count($variants) !== 1) {
            $validator->errors()->add('variants', __('admin.products.errors.simple_one_variant'));

            return;
        }

        // SKUs are unique across all products, including deleted ones (orders keep referring to them).
        $ownIds = array_filter(array_map(fn (array $variant): ?int => isset($variant['id']) ? (int) $variant['id'] : null, $variants));
        $taken = ProductVariant::withTrashed()
            ->whereIn('sku', array_map(fn (array $variant): string => trim((string) $variant['sku']), $variants))
            ->whereNotIn('id', $ownIds ?: [0])
            ->pluck('sku')
            ->map(fn (string $sku): string => mb_strtolower($sku))
            ->all();

        $optionIds = $type === ProductType::Variable ? $this->ints('option_ids') : [];
        $valueOptions = OptionValue::query()
            ->whereIn('id', Arr::flatten(array_map(fn (array $variant): array => $variant['option_value_ids'] ?? [], $variants)))
            ->pluck('option_id', 'id');
        $galleryIds = $this->ints('image_ids');
        $combinations = [];
        $hasEnabled = false;

        foreach ($variants as $index => $variant) {
            if (in_array(mb_strtolower(trim((string) $variant['sku'])), $taken, true)) {
                $validator->errors()->add("variants.{$index}.sku", __('admin.products.errors.sku_taken'));
            }

            if (isset($variant['sale_price']) && (int) $variant['sale_price'] >= (int) $variant['price']) {
                $validator->errors()->add("variants.{$index}.sale_price", __('admin.products.errors.sale_not_lower'));
            }

            if (! empty($variant['sale_starts_at']) && ! empty($variant['sale_ends_at'])
                && CarbonImmutable::parse($variant['sale_ends_at'])->lte(CarbonImmutable::parse($variant['sale_starts_at']))) {
                $validator->errors()->add("variants.{$index}.sale_ends_at", __('admin.products.errors.sale_end_before_start'));
            }

            if (isset($variant['image_id']) && ! in_array((int) $variant['image_id'], $galleryIds, true)) {
                $validator->errors()->add("variants.{$index}.image_id", __('admin.products.errors.image_not_in_gallery'));
            }

            if (isset($variant['id'], $variant['stock_quantity']) && ! isset($variant['stock_quantity_was'])) {
                $validator->errors()->add("variants.{$index}.stock_quantity_was", __('validation.required', ['attribute' => 'stock_quantity_was']));
            }

            $hasEnabled = $hasEnabled || (bool) $variant['is_enabled'];

            if ($type === ProductType::Simple) {
                continue;
            }

            // Exactly one value of each chosen option.
            $values = array_map(intval(...), $variant['option_value_ids'] ?? []);
            $options = array_map(fn (int $valueId): ?int => $valueOptions[$valueId] ?? null, $values);
            sort($options);
            $expected = $optionIds;
            sort($expected);

            if ($options !== $expected) {
                $validator->errors()->add("variants.{$index}.option_value_ids", __('admin.products.errors.variant_options'));

                continue;
            }

            sort($values);
            $key = implode('-', $values);

            if (isset($combinations[$key])) {
                $validator->errors()->add("variants.{$index}.option_value_ids", __('admin.products.errors.variant_duplicate'));
            }

            $combinations[$key] = true;
        }

        if ($this->string('status')->toString() === ProductStatus::Published->value && ! $hasEnabled) {
            $validator->errors()->add('variants', __('admin.products.errors.publish_needs_variant'));
        }
    }

    private function checkCategories(Validator $validator): void
    {
        if ($this->filled('primary_category_id') && ! in_array($this->integer('primary_category_id'), $this->ints('category_ids'), true)) {
            $validator->errors()->add('primary_category_id', __('admin.products.errors.primary_not_in_categories'));
        }
    }

    /**
     * Only specifications of the product's categories (and their ancestors), each with a value
     * of the right kind.
     */
    private function checkSpecifications(Validator $validator): void
    {
        $specifications = array_values((array) $this->input('specifications', []));

        if ($specifications === []) {
            return;
        }

        $applicable = CategoryAttributes::applicable($this->ints('category_ids'))->keyBy('id');

        foreach ($specifications as $index => $specification) {
            /** @var Attribute|null $attribute */
            $attribute = $applicable->get((int) $specification['attribute_id']);

            if ($attribute === null) {
                $validator->errors()->add("specifications.{$index}.attribute_id", __('admin.products.errors.attribute_not_applicable'));

                continue;
            }

            $valid = match ($attribute->type) {
                AttributeType::Select => ($ids = array_map(intval(...), $specification['value_ids'] ?? [])) !== []
                    && array_diff($ids, $attribute->values->pluck('id')->all()) === [],
                AttributeType::Text => trim((string) ($specification['text'] ?? '')) !== '',
                AttributeType::Number => isset($specification['number']) && is_numeric($specification['number']),
                AttributeType::Boolean => isset($specification['boolean']),
            };

            if (! $valid) {
                $validator->errors()->add("specifications.{$index}", __('admin.products.errors.specification_value'));
            }
        }
    }

    /**
     * @return list<SpecificationData>
     */
    private function specificationData(): array
    {
        return array_values(array_map(fn (array $specification): SpecificationData => new SpecificationData(
            attributeId: (int) $specification['attribute_id'],
            valueIds: array_values(array_map(intval(...), $specification['value_ids'] ?? [])),
            text: isset($specification['text']) ? trim((string) $specification['text']) : null,
            number: isset($specification['number']) ? (float) $specification['number'] : null,
            boolean: isset($specification['boolean']) ? (bool) $specification['boolean'] : null,
        ), (array) $this->input('specifications', [])));
    }

    /**
     * @return list<int>
     */
    private function ints(string $key): array
    {
        return array_values(array_map(intval(...), (array) $this->input($key, [])));
    }
}
