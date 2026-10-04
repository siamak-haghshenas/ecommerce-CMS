<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Products;

use App\Data\Products\BulkVariantChange;
use App\Enums\ProductStatus;
use App\Models\ProductVariant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The bulk editor's save: every changed variant of the page, only the changed fields. Amounts are
 * whole Rials. Each variant is checked as it will be after the change; errors use keys like
 * `variants.3.sale_price`, so the page can show them on the row.
 */
class BulkUpdateVariantsRequest extends FormRequest
{
    private const int MAX_AMOUNT = 1_000_000_000_000_000;

    /** @var Collection<int, ProductVariant>|null */
    private ?Collection $loaded = null;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'variants' => ['required', 'array', 'min:1', 'max:2000'],
            'variants.*.id' => ['required', 'integer', 'distinct', Rule::exists('product_variants', 'id')->whereNull('deleted_at')],
            'variants.*.price' => ['sometimes', 'required', 'integer', 'min:0', 'max:'.self::MAX_AMOUNT],
            'variants.*.sale_price' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:'.self::MAX_AMOUNT],
            'variants.*.is_enabled' => ['sometimes', 'required', 'boolean'],
            'variants.*.stock_quantity' => ['sometimes', 'required', 'integer', 'min:0', 'max:10000000'],
            'variants.*.stock_quantity_was' => ['required_with:variants.*.stock_quantity', 'integer'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->checkVariants($validator);
            $this->checkPublishedProducts($validator);
        }];
    }

    /**
     * @return list<BulkVariantChange>
     */
    public function toData(): array
    {
        return array_map(function (array $change): BulkVariantChange {
            $attributes = [];

            if (array_key_exists('price', $change)) {
                $attributes['price'] = (int) $change['price'];
            }

            if (array_key_exists('sale_price', $change)) {
                $attributes['sale_price'] = $change['sale_price'] === null ? null : (int) $change['sale_price'];
            }

            if (array_key_exists('is_enabled', $change)) {
                $attributes['is_enabled'] = (bool) $change['is_enabled'];
            }

            return new BulkVariantChange(
                variantId: (int) $change['id'],
                attributes: $attributes,
                stockQuantity: isset($change['stock_quantity']) ? (int) $change['stock_quantity'] : null,
                stockQuantityWas: isset($change['stock_quantity_was']) ? (int) $change['stock_quantity_was'] : null,
            );
        }, $this->changes());
    }

    /**
     * Sale below the price, stock only for tracked products and only from the number the admin saw,
     * and no variants of deleted products.
     */
    private function checkVariants(Validator $validator): void
    {
        foreach ($this->changes() as $index => $change) {
            $variant = $this->variants()->get((int) $change['id']);

            if ($variant === null) {
                continue;
            }

            if ($variant->product->trashed()) {
                $validator->errors()->add("variants.{$index}.id", __('admin.products.errors.product_deleted'));

                continue;
            }

            $price = array_key_exists('price', $change) ? (int) $change['price'] : $variant->price;
            $sale = array_key_exists('sale_price', $change)
                ? ($change['sale_price'] === null ? null : (int) $change['sale_price'])
                : $variant->sale_price;

            if ($sale !== null && $sale >= $price) {
                $field = array_key_exists('sale_price', $change) ? 'sale_price' : 'price';
                $validator->errors()->add("variants.{$index}.{$field}", __('admin.products.errors.sale_not_lower'));
            }

            if (! array_key_exists('stock_quantity', $change)) {
                continue;
            }

            if (! $variant->product->track_stock) {
                $validator->errors()->add("variants.{$index}.stock_quantity", __('admin.products.errors.stock_not_tracked'));
            } elseif ((int) $change['stock_quantity_was'] !== $variant->stock_quantity) {
                $validator->errors()->add("variants.{$index}.stock_quantity", __('admin.products.errors.stock_changed_to', ['quantity' => $variant->stock_quantity]));
            }
        }
    }

    /**
     * A published product keeps at least one enabled variant (counting its variants not on the page).
     */
    private function checkPublishedProducts(Validator $validator): void
    {
        $enabledChanges = [];

        foreach ($this->changes() as $index => $change) {
            if (array_key_exists('is_enabled', $change)) {
                $enabledChanges[(int) $change['id']] = [$index, (bool) $change['is_enabled']];
            }
        }

        $products = $this->variants()
            ->filter(fn (ProductVariant $variant): bool => isset($enabledChanges[$variant->id]) && $variant->product->status === ProductStatus::Published)
            ->map(fn (ProductVariant $variant) => $variant->product)
            ->unique('id');

        foreach ($products as $product) {
            $stillEnabled = $product->variants->contains(
                fn (ProductVariant $variant): bool => $enabledChanges[$variant->id][1] ?? $variant->is_enabled,
            );

            if ($stillEnabled) {
                continue;
            }

            foreach ($product->variants as $variant) {
                if (isset($enabledChanges[$variant->id])) {
                    $validator->errors()->add("variants.{$enabledChanges[$variant->id][0]}.is_enabled", __('admin.products.errors.publish_needs_variant'));
                }
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function changes(): array
    {
        return array_values((array) $this->input('variants', []));
    }

    /**
     * The variants being changed, with their product (deleted ones too) and its variants.
     *
     * @return Collection<int, ProductVariant>
     */
    private function variants(): Collection
    {
        return $this->loaded ??= ProductVariant::query()
            ->whereKey(array_map(fn (array $change): int => (int) $change['id'], $this->changes()))
            ->with('product.variants')
            ->get()
            ->keyBy('id');
    }
}
