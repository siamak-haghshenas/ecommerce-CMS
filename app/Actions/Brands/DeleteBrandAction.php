<?php

declare(strict_types=1);

namespace App\Actions\Brands;

use App\Actions\Seo\ScoreSeoAction;
use App\Models\Brand;
use App\Models\User;

final readonly class DeleteBrandAction
{
    public function __construct(private ScoreSeoAction $scoreSeo) {}

    /**
     * Delete a brand and its logo. (Refused while it has products, once products exist.)
     */
    public function handle(User $actor, Brand $brand): void
    {
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
