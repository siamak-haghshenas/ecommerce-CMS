<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A free label across categories («هدیه», "summer"). Created on the fly from the product form;
 * the slug lets storefronts filter by tag.
 */
#[Fillable(['name', 'slug'])]
class Tag extends Model
{
    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}
