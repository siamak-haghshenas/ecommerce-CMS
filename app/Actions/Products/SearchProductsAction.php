<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Data\Products\ProductFilters;
use App\Models\Product;
use App\Support\CategoryTree;
use App\Support\Seo\SeoText;
use App\Support\Settings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class SearchProductsAction
{
    public function __construct(private Settings $settings) {}

    /**
     * The admin product list.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function handle(ProductFilters $filters): LengthAwarePaginator
    {
        $query = Product::query()
            ->with(['brand', 'categories', 'variants', 'media'])
            ->when($filters->trashed, fn (Builder $query) => $query->onlyTrashed());

        // Every word must appear, typed with any keyboard ("كفش ۴۲" finds "کفش 42").
        foreach (SeoText::words((string) $filters->search) as $word) {
            $query->where('search_text', 'like', '%'.addcslashes($word, '%_\\').'%');
        }

        $query
            ->when($filters->status, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters->brandId, fn (Builder $query, int $id) => $query->where('brand_id', $id))
            ->when($filters->tagId, fn (Builder $query, int $id) => $query->whereHas('tags', fn (Builder $tags) => $tags->whereKey($id)))
            // A category includes its sub-categories.
            ->when($filters->categoryId, fn (Builder $query, int $id) => $query->whereHas('categories', fn (Builder $categories) => $categories->whereKey(
                [$id, ...CategoryTree::current()->descendantIds($id)],
            )))
            ->when($filters->needsSeo, fn (Builder $query) => $query->where(fn (Builder $seo) => $seo->whereNull('seo_score')->orWhere('seo_score', '<', 70)));

        if ($filters->stock !== null) {
            $this->filterStock($query, $filters->stock);
        }

        match ($filters->sort) {
            'name' => $query->orderBy('name'),
            'updated' => $query->latest('updated_at'),
            default => $query->latest('id'),
        };

        return $query->paginate($filters->perPage);
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function filterStock(Builder $query, string $stock): void
    {
        $query->where('track_stock', true);

        if ($stock === 'low') {
            // An enabled variant at or below the product's threshold (or the store default).
            $query->whereHas('variants', fn (Builder $variants) => $variants
                ->where('is_enabled', true)
                ->where('stock_quantity', '<=', DB::raw('COALESCE(products.low_stock_threshold, '.$this->settings->lowStockThreshold().')')));

            return;
        }

        // Out: nothing left to sell and no backorders.
        $query->where('allow_backorders', false)
            ->whereDoesntHave('variants', fn (Builder $variants) => $variants->where('is_enabled', true)->where('stock_quantity', '>', 0));
    }
}
