<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;

final class ChangeProductStatusAction
{
    /**
     * Set the status of several products (bulk publish / archive / back to draft). Products that
     * can't be published (no category or no enabled variant) are skipped and returned.
     *
     * @param  list<int>  $productIds
     * @return array{updated: list<int>, skipped: list<int>}
     */
    public function handle(User $actor, array $productIds, ProductStatus $status): array
    {
        $result = ['updated' => [], 'skipped' => []];

        foreach (Product::query()->whereKey($productIds)->get() as $product) {
            $publishable = $product->categories()->exists() && $product->variants()->where('is_enabled', true)->exists();

            if ($status === ProductStatus::Published && ! $publishable) {
                $result['skipped'][] = $product->id;

                continue;
            }

            if ($product->status === $status) {
                continue;
            }

            $product->status = $status;
            $product->published_at = $status === ProductStatus::Published ? ($product->published_at ?? now()) : null;
            $product->save();
            $result['updated'][] = $product->id;

            activity('admin')
                ->causedBy($actor)
                ->performedOn($product)
                ->event('product.updated')
                ->withProperties(['name' => $product->name, 'fields' => ['status']])
                ->log('product.updated');
        }

        return $result;
    }
}
