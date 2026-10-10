<?php

declare(strict_types=1);

namespace App\Data\SaleEvents;

use Carbon\CarbonImmutable;

final readonly class SaleEventData
{
    /**
     * @param  list<SaleEventItemData>  $items  every variant of the event (missing ones are removed)
     */
    public function __construct(
        public string $name,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public bool $isActive,
        public array $items,
        public bool $hasLandingPage,
        public ?string $slug,
        public ?string $landingTitle,
        /** Sanitized rich text. */
        public ?string $landingDescription,
        public ?int $bannerId,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public ?string $focusKeyphrase,
        public bool $robotsNoindex,
        public ?string $canonicalUrl,
        public ?string $ogTitle,
        public ?string $ogDescription,
        public ?int $ogImageId,
    ) {}
}
