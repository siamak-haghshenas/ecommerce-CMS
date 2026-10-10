<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Data\Catalog\StorefrontProductFilters;
use App\Enums\CategoryType;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Support\CategoryTree;
use App\Support\Seo\SeoText;
use Illuminate\Database\Eloquent\Builder;

/**
 * The product queries of the Storefront API. A product is visible when it is published, its
 * publish time has passed, it is not deleted, it has an enabled variant and at least one of its
 * categories is visible (hiding a category branch hides products that are only in it).
 */
final class StorefrontProductQuery
{
    /**
     * The price shoppers pay now: the stored lowest of the regular price, the sale price and the
     * promotions that apply (ProductVariant::currentPrice(), kept up to date by RefreshPricesAction).
     */
    public const string CURRENT_PRICE = 'COALESCE(product_variants.current_price, product_variants.price)';

    /**
     * @return Builder<Product>
     */
    public static function visible(): Builder
    {
        $visibleCategoryIds = CategoryTree::current()->visibleIds(CategoryType::Product);

        return Product::query()
            ->where('status', ProductStatus::Published)
            ->where('published_at', '<=', now())
            ->whereHas('variants', fn (Builder $variants) => $variants->where('is_enabled', true))
            ->whereHas('categories', fn (Builder $categories) => $categories->whereKey($visibleCategoryIds ?: [0]));
    }

    /**
     * The filters that define "where" the shopper is (search, category, brand, tag, sale event): the base
     * of the filter counts.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function applyScope(Builder $query, StorefrontProductFilters $filters): Builder
    {
        foreach (SeoText::words((string) $filters->search) as $word) {
            $query->where('search_text', 'like', '%'.addcslashes($word, '%_\\').'%');
        }

        if ($filters->categoryId !== null) {
            $tree = CategoryTree::current();
            $ids = $tree->isVisible($filters->categoryId)
                ? array_values(array_intersect([$filters->categoryId, ...$tree->descendantIds($filters->categoryId)], $tree->visibleIds(CategoryType::Product)))
                : [0];
            $query->whereHas('categories', fn (Builder $categories) => $categories->whereKey($ids));
        }

        return $query
            ->when($filters->brandId !== null, fn (Builder $query) => $query->where('brand_id', $filters->brandId))
            ->when($filters->tagId !== null, fn (Builder $query) => $query->whereHas('tags', fn (Builder $tags) => $tags->whereKey($filters->tagId)))
            // A landing page lists the products with an enabled variant in the event.
            ->when($filters->saleEventId !== null, fn (Builder $query) => $query->whereHas('variants', fn (Builder $variants) => $variants
                ->where('is_enabled', true)
                ->whereHas('saleEventItems', fn (Builder $items) => $items->where('sale_event_id', $filters->saleEventId))));
    }

    /**
     * The refining filters: specifications, options, price, stock and sale.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function applyRefinements(Builder $query, StorefrontProductFilters $filters): Builder
    {
        foreach ($filters->attributes as $attributeId => $condition) {
            $query->whereHas('attributeValues', function (Builder $values) use ($attributeId, $condition): void {
                $values->where('attribute_id', $attributeId);

                match (true) {
                    is_bool($condition) => $values->where('value_boolean', $condition),
                    array_key_exists('min', $condition) => $values
                        ->when($condition['min'] !== null, fn (Builder $q) => $q->where('value_number', '>=', $condition['min']))
                        ->when($condition['max'] !== null, fn (Builder $q) => $q->where('value_number', '<=', $condition['max'])),
                    default => $values->whereIn('attribute_value_id', $condition),
                };
            });
        }

        // One enabled variant must match every chosen option ("red" and "XL" in the same variant).
        if ($filters->options !== []) {
            $query->whereHas('variants', function (Builder $variants) use ($filters): void {
                $variants->where('is_enabled', true);

                foreach ($filters->options as $valueIds) {
                    $variants->whereHas('optionValues', fn (Builder $values) => $values->whereKey($valueIds));
                }
            });
        }

        if ($filters->minPrice !== null || $filters->maxPrice !== null) {
            $query->whereHas('variants', fn (Builder $variants) => $variants
                ->where('is_enabled', true)
                ->when($filters->minPrice !== null, fn (Builder $q) => $q->whereRaw(self::CURRENT_PRICE.' >= ?', [$filters->minPrice]))
                ->when($filters->maxPrice !== null, fn (Builder $q) => $q->whereRaw(self::CURRENT_PRICE.' <= ?', [$filters->maxPrice])));
        }

        if ($filters->inStock) {
            $query->where(fn (Builder $stock) => $stock
                ->where('track_stock', false)
                ->orWhere('allow_backorders', true)
                ->orWhereHas('variants', fn (Builder $variants) => $variants->where('is_enabled', true)->where('stock_quantity', '>', 0)));
        }

        if ($filters->onSale) {
            $query->whereHas('variants', fn (Builder $variants) => $variants
                ->where('is_enabled', true)
                ->whereRaw(self::CURRENT_PRICE.' < product_variants.price'));
        }

        return $query;
    }

    /**
     * Adds `min_price` (the lowest current price of the enabled variants), used for sorting.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function withMinPrice(Builder $query): Builder
    {
        return $query->select('products.*')->selectSub(
            fn ($sub) => $sub->from('product_variants')
                ->selectRaw('MIN('.self::CURRENT_PRICE.')')
                ->whereColumn('product_variants.product_id', 'products.id')
                ->where('product_variants.is_enabled', true)
                ->whereNull('product_variants.deleted_at'),
            'min_price',
        );
    }
}
