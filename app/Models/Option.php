<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OptionDisplayType;
use Database\Factories\OptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A variant dimension (Size, Color). Product variants are generated from option value combinations.
 */
#[Fillable(['name', 'slug', 'display_type', 'position'])]
class Option extends Model
{
    /** @use HasFactory<OptionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'display_type' => OptionDisplayType::class,
            'position' => 'integer',
        ];
    }

    /**
     * @return HasMany<OptionValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(OptionValue::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }
}
