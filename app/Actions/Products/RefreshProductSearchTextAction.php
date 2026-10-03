<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Seo\SeoText;
use Illuminate\Database\Eloquent\Builder;

/**
 * The normalized text admin and storefront search look in: name, brand names, SKUs, tags and
 * categories. Normalized like the SEO analysis, so Arabic/Persian letters, half-spaces and
 * digits match however they were typed.
 */
final class RefreshProductSearchTextAction
{
    public function handle(Product $product): void
    {
        $product->load(['brand', 'tags', 'categories', 'variants']);

        $parts = [
            $product->name,
            $product->brand?->name,
            $product->brand?->persian_name,
            ...$product->variants->map(fn (ProductVariant $variant): string => $variant->sku)->all(),
            ...$product->tags->pluck('name')->all(),
            ...$product->categories->pluck('name')->all(),
        ];

        $text = implode(' ', SeoText::words(implode(' ', array_filter($parts))));

        if ($text !== $product->search_text) {
            Product::withTrashed()->whereKey($product->id)->update(['search_text' => $text]);
            $product->forceFill(['search_text' => $text])->syncOriginalAttribute('search_text');
        }
    }

    /**
     * Refresh every product matching the query, e.g. after a brand or category was renamed.
     *
     * @param  Builder<Product>  $query
     */
    public function forQuery(Builder $query): void
    {
        $query->withTrashed()->lazyById(200)->each(fn (Product $product) => $this->handle($product));
    }
}
