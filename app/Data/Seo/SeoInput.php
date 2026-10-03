<?php

declare(strict_types=1);

namespace App\Data\Seo;

final readonly class SeoInput
{
    /**
     * @param  list<string>  $keyphraseUsedBy  names of other items using the same focus keyphrase
     */
    public function __construct(
        public string $name,
        public string $slug,
        public string $description,
        public string $metaTitle,
        public string $metaDescription,
        public string $focusKeyphrase,
        public bool $hasImage,
        public array $keyphraseUsedBy,
    ) {}
}
