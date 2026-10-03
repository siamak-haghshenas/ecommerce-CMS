<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Data\Seo\SeoInput;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Implements SeoSubject for a model with `name`, `slug`, `description` and the shared SEO columns.
 *
 * @mixin Model
 * @mixin InteractsWithMedia
 */
trait HasSeo
{
    /**
     * @param  list<string>  $keyphraseUsedBy
     */
    public function seoInput(array $keyphraseUsedBy): SeoInput
    {
        return new SeoInput(
            name: (string) $this->getAttribute('name'),
            slug: (string) $this->getAttribute('slug'),
            description: $this->seoDescription(),
            metaTitle: (string) $this->getAttribute('meta_title'),
            metaDescription: (string) $this->getAttribute('meta_description'),
            focusKeyphrase: (string) $this->getAttribute('focus_keyphrase'),
            hasImage: $this->hasMedia($this->seoImageCollection()),
            keyphraseUsedBy: $keyphraseUsedBy,
        );
    }

    /**
     * The description as plain text; models storing HTML override this.
     */
    public function seoDescription(): string
    {
        return (string) $this->getAttribute('description');
    }
}
