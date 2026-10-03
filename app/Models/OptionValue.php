<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['option_id', 'value', 'slug', 'color_hex', 'position'])]
class OptionValue extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'option_id' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Option, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class);
    }
}
