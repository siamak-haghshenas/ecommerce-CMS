<?php

declare(strict_types=1);

namespace App\Actions\Brands;

use App\Exceptions\BrandException;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ReorderBrandsAction
{
    /**
     * Save a new manual order. The ids must be exactly the current brands, so an admin
     * working from an outdated list can't silently misplace brands added in the meantime.
     *
     * @param  list<int>  $ids
     *
     * @throws BrandException
     */
    public function handle(User $actor, array $ids): void
    {
        DB::transaction(function () use ($actor, $ids): void {
            $brands = Brand::query()->lockForUpdate()->get()->keyBy('id');

            $current = $brands->keys()->all();
            sort($current);
            $sent = $ids;
            sort($sent);

            if ($current !== $sent) {
                throw BrandException::listChanged();
            }

            $changed = false;

            foreach ($ids as $position => $id) {
                /** @var Brand $brand */
                $brand = $brands->get($id);
                $brand->position = $position;

                if ($brand->isDirty()) {
                    $brand->save();
                    $changed = true;
                }
            }

            if ($changed) {
                activity('admin')->causedBy($actor)->event('brand.reordered')->log('brand.reordered');
            }
        });
    }
}
