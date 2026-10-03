<?php

declare(strict_types=1);

namespace App\Data\Seo;

use App\Contracts\SeoSubject;
use App\Enums\SeoContentType;
use Illuminate\Database\Eloquent\Model;

/**
 * What the admin has typed so far (possibly unsaved), used to ask AI for SEO suggestions.
 */
final readonly class SeoSuggestionContext
{
    /**
     * @param  string|null  $alternateName  another way the name is written, e.g. a brand's Persian name
     * @param  list<string>  $parentPath  parent names, top first (categories)
     * @param  (Model&SeoSubject)|null  $subject  the saved item, when it exists
     */
    public function __construct(
        public SeoContentType $type,
        public string $name,
        public ?string $description,
        public ?string $focusKeyphrase,
        public ?string $alternateName = null,
        public array $parentPath = [],
        public ?Model $subject = null,
    ) {}
}
