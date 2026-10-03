<?php

declare(strict_types=1);

namespace App\Data\Seo;

final readonly class SeoSuggestions
{
    /**
     * @param  list<string>  $keyphrases
     * @param  list<string>  $titles
     * @param  list<string>  $metaDescriptions
     */
    public function __construct(
        public array $keyphrases,
        public array $titles,
        public array $metaDescriptions,
    ) {}
}
