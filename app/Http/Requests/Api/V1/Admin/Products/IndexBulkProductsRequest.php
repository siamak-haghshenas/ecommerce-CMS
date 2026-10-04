<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Products;

/**
 * The bulk editor's page of products: the product list's filters, never deleted products.
 */
class IndexBulkProductsRequest extends IndexProductsRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['trashed' => false]);
    }
}
