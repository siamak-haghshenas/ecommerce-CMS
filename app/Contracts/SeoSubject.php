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
     * The item's name (a page's title), e.g. to list the other items using a keyphrase.
     */
    public function seoName(): string;

    /**
     * Whether the item has a picture (the `image` check): a product's gallery, a category's image, a brand's logo.
     */
    public function hasSeoImage(): bool;

    /**
     * Column values shared by the items whose focus keyphrase this one must not repeat (e.g. a
     * category's type); empty when every row of the table is the same content type.
     *
     * @return array<string, string>
     */
    public function seoPeerConstraints(): array;
}
