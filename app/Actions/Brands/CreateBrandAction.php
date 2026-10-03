<?php

declare(strict_types=1);

namespace App\Actions\Brands;

use App\Actions\Seo\ScoreSeoAction;
use App\Data\Brands\BrandData;
use App\Models\Brand;
use App\Models\User;

final readonly class CreateBrandAction
{
    public function __construct(private ScoreSeoAction $scoreSeo) {}

    /**
     * Create a brand at the end of the list.
     */
    public function handle(User $actor, BrandData $data): Brand
    {
        $brand = Brand::query()->create([
            'name' => $data->name,
            'persian_name' => $data->persianName,
            'slug' => $data->slug,
            'description' => $data->description,
            'website' => $data->website,
            'is_active' => $data->isActive,
            'meta_title' => $data->metaTitle,
            'meta_description' => $data->metaDescription,
            'focus_keyphrase' => $data->focusKeyphrase,
            'position' => Brand::nextPosition(),
        ]);

        $this->scoreSeo->handle($brand);

        activity('admin')
            ->causedBy($actor)
            ->performedOn($brand)
            ->event('brand.created')
            ->log('brand.created');

        return $brand;
    }
}
