<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * The `slug` of public content (categories, brands, ...): Latin only, lowercase letters and
 * digits separated by single hyphens, unique in its table. Storefronts build URLs from it.
 */
trait HasSlugField
{
    /** Latin only: lowercase letters and digits, separated by single hyphens. */
    public const string SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * Call from prepareForValidation(): " Mens-Shirts " is accepted as "mens-shirts".
     */
    protected function normalizeSlug(): void
    {
        if (is_string($this->input('slug'))) {
            $this->merge(['slug' => mb_strtolower(trim($this->string('slug')->toString()))]);
        }
    }

    /**
     * @return list<mixed>
     */
    protected function slugRules(string $table, ?int $ignoreId = null): array
    {
        return [
            'required',
            'string',
            'max:120',
            'regex:'.self::SLUG_PATTERN,
            Rule::unique($table, 'slug')->ignore($ignoreId),
        ];
    }
}
