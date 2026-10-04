<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\MediaType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Pictures chosen from the media library: an image file that is not in the trash.
 */
trait HasMediaFields
{
    protected function libraryImageRule(): Exists
    {
        return Rule::exists('media_assets', 'id')
            ->where('type', MediaType::Image->value)
            ->whereNull('deleted_at');
    }

    /**
     * The id of one picture, or null.
     */
    protected function libraryImageId(string $key): ?int
    {
        return $this->filled($key) ? $this->integer($key) : null;
    }
}
