<?php

declare(strict_types=1);

namespace App\Actions\Brands;

use App\Actions\Seo\ScoreSeoAction;
use App\Models\Brand;
use App\Models\User;

final readonly class RemoveBrandLogoAction
{
    public function __construct(private ScoreSeoAction $scoreSeo) {}

    public function handle(User $actor, Brand $brand): Brand
    {
        if (! $brand->hasMedia(Brand::LOGO_COLLECTION)) {
            return $brand;
        }

        $brand->clearMediaCollection(Brand::LOGO_COLLECTION);
        $brand->touch();
        $this->scoreSeo->score($brand->load('media'));

        activity('admin')
            ->causedBy($actor)
            ->performedOn($brand)
            ->event('brand.updated')
            ->withProperties(['fields' => ['logo']])
            ->log('brand.updated');

        return $brand->load('media');
    }
}
