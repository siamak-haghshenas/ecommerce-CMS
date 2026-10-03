<?php

declare(strict_types=1);

namespace App\Actions\Brands;

use App\Actions\Seo\ScoreSeoAction;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Http\UploadedFile;

final readonly class UpdateBrandLogoAction
{
    public function __construct(private ScoreSeoAction $scoreSeo) {}

    /**
     * Replace the logo (the collection holds a single file) and generate its conversions.
     */
    public function handle(User $actor, Brand $brand, UploadedFile $logo): Brand
    {
        $brand->addMedia($logo)->toMediaCollection(Brand::LOGO_COLLECTION);
        $brand->touch();

        // The image check is part of the SEO score.
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
