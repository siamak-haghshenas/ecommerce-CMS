<?php

declare(strict_types=1);

namespace App\Actions\Brands;

use App\Actions\Seo\ScoreSeoAction;
use App\Exceptions\BrandException;
use App\Models\Brand;
use App\Models\Product;
use App\Models\User;

final readonly class DeleteBrandAction
{
    public function __construct(private ScoreSeoAction $scoreSeo) {}

    /**
     * Delete a brand and its logo. Refused while products have it (deleted products too,
     * since orders keep referring to them); hiding the brand is the alternative.
     *
     * @throws BrandException
     */
    public function handle(User $actor, Brand $brand): void
    {
        if (Product::withTrashed()->where('brand_id', $brand->id)->exists()) {
            throw BrandException::hasProducts();
        }

        // Brand names are not personal data; keeping them makes the entry readable after deletion.
        activity('admin')
            ->causedBy($actor)
            ->performedOn($brand)
            ->event('brand.deleted')
            ->withProperties(['name' => $brand->name, 'slug' => $brand->slug])
            ->log('brand.deleted');

        $brand->delete();

        // Brands that shared its keyphrase no longer have a duplicate.
        if ($brand->focus_keyphrase !== null) {
            $this->scoreSeo->scoreSharing($brand, $brand->focus_keyphrase);
        }
    }
}
