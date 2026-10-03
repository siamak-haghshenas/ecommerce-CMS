<?php

declare(strict_types=1);

namespace App\Actions\Brands;

use App\Actions\Products\RefreshProductSearchTextAction;
use App\Actions\Seo\ScoreSeoAction;
use App\Data\Brands\BrandData;
use App\Models\Brand;
use App\Models\Product;
use App\Models\User;

final readonly class UpdateBrandAction
{
    public function __construct(
        private ScoreSeoAction $scoreSeo,
        private RefreshProductSearchTextAction $refreshSearchText,
    ) {}

    public function handle(User $actor, Brand $brand, BrandData $data): Brand
    {
        $previousKeyphrase = $brand->focus_keyphrase;

        $brand->fill([
            'name' => $data->name,
            'persian_name' => $data->persianName,
            'slug' => $data->slug,
            'description' => $data->description,
            'website' => $data->website,
            'is_active' => $data->isActive,
            'meta_title' => $data->metaTitle,
            'meta_description' => $data->metaDescription,
            'focus_keyphrase' => $data->focusKeyphrase,
        ]);

        $changedFields = array_keys($brand->getDirty());

        if ($changedFields === []) {
            return $brand;
        }

        $brand->save();

        // Product search includes brand names.
        if (array_intersect($changedFields, ['name', 'persian_name']) !== []) {
            $this->refreshSearchText->forQuery(Product::query()->where('brand_id', $brand->id));
        }
        $this->scoreSeo->handle($brand, $previousKeyphrase);

        activity('admin')
            ->causedBy($actor)
            ->performedOn($brand)
            ->event('brand.updated')
            ->withProperties(['fields' => $changedFields])
            ->log('brand.updated');

        return $brand;
    }
}
