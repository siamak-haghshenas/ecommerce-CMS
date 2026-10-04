<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Actions\Seo\ScoreSeoAction;
use App\Data\Products\ProductData;
use App\Data\Products\SpecificationData;
use App\Data\Products\VariantData;
use App\Enums\MediaUsageRole;
use App\Enums\ProductStatus;
use App\Enums\StockMovementReason;
use App\Exceptions\ProductException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a product with everything in its form: categories, tags, options,
 * variants (with stock) and specifications. The request has validated the structure.
 */
final readonly class SaveProductAction
{
    public function __construct(
        private ResolveTagsAction $resolveTags,
        private AdjustStockAction $adjustStock,
        private RefreshProductSearchTextAction $refreshSearchText,
        private ScoreSeoAction $scoreSeo,
    ) {}

    /**
     * @throws ProductException when stock changed since the admin loaded the form
     */
    public function handle(User $actor, ?Product $product, ProductData $data): Product
    {
        return DB::transaction(function () use ($actor, $product, $data): Product {
            $isNew = $product === null;
            $product ??= new Product;
            $previousKeyphrase = $product->focus_keyphrase;

            $product->fill([
                'type' => $data->type,
                'name' => $data->name,
                'slug' => $data->slug,
                'short_description' => $data->shortDescription,
                'description' => $data->description,
                'status' => $data->status,
                // Publishing without a date publishes now; a future date schedules it.
                'published_at' => $data->publishedAt ?? ($data->status === ProductStatus::Published ? ($product->published_at ?? now()) : null),
                'brand_id' => $data->brandId,
                'track_stock' => $data->trackStock,
                'allow_backorders' => $data->allowBackorders,
                'low_stock_threshold' => $data->lowStockThreshold,
                'weight_grams' => $data->weightGrams,
                'length_mm' => $data->lengthMm,
                'width_mm' => $data->widthMm,
                'height_mm' => $data->heightMm,
                'meta_title' => $data->metaTitle,
                'meta_description' => $data->metaDescription,
                'focus_keyphrase' => $data->focusKeyphrase,
            ]);

            $changed = array_keys($product->getDirty());
            $product->save();

            if ($this->syncCategories($product, $data)) {
                $changed[] = 'categories';
            }

            if (array_filter($product->tags()->sync($this->resolveTags->handle($data->tagNames))) !== []) {
                $changed[] = 'tags';
            }

            $options = [];
            foreach ($data->optionIds as $position => $optionId) {
                $options[$optionId] = ['position' => $position];
            }
            if (array_filter($product->options()->sync($options)) !== []) {
                $changed[] = 'options';
            }

            // Library images and videos in the description are in use while it shows them.
            $product->syncMediaAssets(MediaUsageRole::Content, RichText::mediaIds($product->description));

            if ($product->syncMediaAssets(MediaUsageRole::Gallery, $data->imageIds)) {
                $changed[] = 'images';
            }

            if ($this->syncVariants($actor, $product, $data->variants)) {
                $changed[] = 'variants';
            }

            if ($this->syncSpecifications($product, $data->specifications)) {
                $changed[] = 'specifications';
            }

            $this->refreshSearchText->handle($product);
            $this->scoreSeo->handle($product, $previousKeyphrase);

            if ($isNew || $changed !== []) {
                $event = $isNew ? 'product.created' : 'product.updated';

                activity('admin')
                    ->causedBy($actor)
                    ->performedOn($product)
                    ->event($event)
                    ->withProperties($isNew ? ['name' => $product->name] : ['name' => $product->name, 'fields' => array_values(array_unique($changed))])
                    ->log($event);
            }

            return $product;
        });
    }

    private function syncCategories(Product $product, ProductData $data): bool
    {
        $categories = [];
        foreach ($data->categoryIds as $categoryId) {
            $categories[$categoryId] = ['is_primary' => $categoryId === $data->primaryCategoryId];
        }

        return array_filter($product->categories()->sync($categories)) !== [];
    }

    /**
     * Update variants by id, create new ones, soft-delete missing ones (orders may point at them).
     *
     * @param  list<VariantData>  $variants
     */
    private function syncVariants(User $actor, Product $product, array $variants): bool
    {
        $existing = $product->variants()->get()->keyBy('id');
        $keptIds = array_filter(array_map(fn (VariantData $variant): ?int => $variant->id, $variants));
        $changed = false;

        foreach ($existing->except($keptIds) as $removed) {
            // Soft delete: its SKU stays taken, as orders may refer to it.
            $removed->delete();
            $changed = true;
        }

        foreach ($variants as $position => $data) {
            $variant = $data->id === null ? new ProductVariant(['product_id' => $product->id]) : $existing[$data->id];

            $variant->fill([
                'sku' => $data->sku,
                'price' => $data->price,
                'sale_price' => $data->salePrice,
                'sale_starts_at' => $data->saleStartsAt,
                'sale_ends_at' => $data->saleEndsAt,
                'is_enabled' => $data->isEnabled,
                'image_asset_id' => $data->imageId,
                'position' => $position,
            ]);

            $changed = $changed || $variant->isDirty();
            $isNew = ! $variant->exists;
            $variant->save();

            if (array_filter($variant->optionValues()->sync($data->optionValueIds)) !== []) {
                $changed = true;
            }

            if ($data->stockQuantity === null) {
                continue;
            }

            if ($isNew) {
                $this->adjustStock->handle($variant, $data->stockQuantity, StockMovementReason::Initial, $actor);
                $changed = $changed || $data->stockQuantity !== 0;

                continue;
            }

            // The admin edited the number they saw: if stock moved meanwhile, don't overwrite it.
            if ($variant->stock_quantity !== $data->stockQuantityWas) {
                throw ProductException::stockChanged($position);
            }

            if ($data->stockQuantity !== $variant->stock_quantity) {
                $this->adjustStock->handle($variant, $data->stockQuantity - $variant->stock_quantity, StockMovementReason::Adjustment, $actor);
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * Replace the specification rows; a "select" attribute gets one row per chosen value.
     *
     * @param  list<SpecificationData>  $specifications
     */
    private function syncSpecifications(Product $product, array $specifications): bool
    {
        $rows = [];

        foreach ($specifications as $specification) {
            foreach ($specification->valueIds ?: [null] as $valueId) {
                $rows[] = [
                    'attribute_id' => $specification->attributeId,
                    'attribute_value_id' => $valueId,
                    'value_text' => $valueId === null ? $specification->text : null,
                    'value_number' => $valueId === null && $specification->number !== null ? round($specification->number, 3) : null,
                    'value_boolean' => $valueId === null ? $specification->boolean : null,
                ];
            }
        }

        $current = $product->attributeValues()->get()
            ->map(fn ($row): array => [
                'attribute_id' => $row->attribute_id,
                'attribute_value_id' => $row->attribute_value_id,
                'value_text' => $row->value_text,
                'value_number' => $row->value_number,
                'value_boolean' => $row->value_boolean,
            ])
            ->all();

        $normalize = function (array $list): array {
            $list = array_map(fn (array $row): string => json_encode([
                $row['attribute_id'], $row['attribute_value_id'], $row['value_text'],
                $row['value_number'] === null ? null : (float) $row['value_number'], $row['value_boolean'],
            ], JSON_THROW_ON_ERROR), $list);
            sort($list);

            return $list;
        };

        if ($normalize($rows) === $normalize($current)) {
            return false;
        }

        $product->attributeValues()->delete();
        $product->attributeValues()->createMany($rows);

        return true;
    }
}
