<?php

declare(strict_types=1);

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\User;

final class DeleteProductAction
{
    /**
     * Soft-delete a product (it can be restored); orders will keep pointing at it.
     */
    public function handle(User $actor, Product $product): void
    {
        $product->delete();

        activity('admin')
            ->causedBy($actor)
            ->performedOn($product)
            ->event('product.deleted')
            ->withProperties(['name' => $product->name])
            ->log('product.deleted');
    }

    public function restore(User $actor, Product $product): void
    {
        $product->restore();

        activity('admin')
            ->causedBy($actor)
            ->performedOn($product)
            ->event('product.restored')
            ->withProperties(['name' => $product->name])
            ->log('product.restored');
    }
}
