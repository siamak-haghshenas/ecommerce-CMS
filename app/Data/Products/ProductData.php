<?php

declare(strict_types=1);

namespace App\Data\Products;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use Carbon\CarbonImmutable;

final readonly class ProductData
{
    /**
     * @param  list<int>  $categoryIds
     * @param  list<string>  $tagNames  existing or new tags, by name
     * @param  list<int>  $optionIds  in order; empty for a simple product
     * @param  list<VariantData>  $variants  in order; exactly one for a simple product
     * @param  list<SpecificationData>  $specifications
     * @param  list<int>  $imageIds  the gallery (media library images) in order
     */
    public function __construct(
        public ProductType $type,
        public string $name,
        public string $slug,
        public ?string $shortDescription,
        public ?string $description,
        public ProductStatus $status,
        public ?CarbonImmutable $publishedAt,
        public ?int $brandId,
        public array $categoryIds,
        public ?int $primaryCategoryId,
        public array $tagNames,
        public bool $trackStock,
        public bool $allowBackorders,
        public ?int $lowStockThreshold,
        public ?int $weightGrams,
        public ?int $lengthMm,
        public ?int $widthMm,
        public ?int $heightMm,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public ?string $focusKeyphrase,
        public array $optionIds,
        public array $variants,
        public array $specifications,
        /** The gallery: media library images in order; the first is the main image. */
        public array $imageIds = [],
    ) {}
}
