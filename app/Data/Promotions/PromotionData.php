<?php

declare(strict_types=1);

namespace App\Data\Promotions;

use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use Carbon\CarbonImmutable;

final readonly class PromotionData
{
    /**
     * @param  list<int>  $productIds
     * @param  list<int>  $categoryIds  product categories (sub-categories are included)
     * @param  list<int>  $brandIds
     * @param  list<int>  $tagIds
     * @param  list<int>  $excludedProductIds
     */
    public function __construct(
        public string $name,
        public PromotionType $type,
        /** A percentage (1–90) or Rials. */
        public int $value,
        public ?int $maxDiscount,
        public PromotionScope $scope,
        public array $productIds,
        public array $categoryIds,
        public array $brandIds,
        public array $tagIds,
        public array $excludedProductIds,
        /** Null: now. */
        public ?CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public bool $isActive,
    ) {}
}
