<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\Seo\SeoInput;

/**
 * Content with a public page and the shared SEO fields (`meta_title`, `meta_description`,
 * `focus_keyphrase`, `seo_score`, `readability_score`). Implemented through the HasSeo trait.
 */
interface SeoSubject
{
    /**
     * The analyzer's view of this item.
     *
     * @param  list<string>  $keyphraseUsedBy  names of other items of the same type using its focus keyphrase
     */
    public function seoInput(array $keyphraseUsedBy): SeoInput;

    /**
     * The media collection whose image counts for the `image` check.
     */
    public function seoImageCollection(): string;
}
