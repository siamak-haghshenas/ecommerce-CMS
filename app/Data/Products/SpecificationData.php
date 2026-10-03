<?php

declare(strict_types=1);

namespace App\Data\Products;

/**
 * A product's value for one attribute. Which field is used depends on the attribute type.
 */
final readonly class SpecificationData
{
    /**
     * @param  list<int>  $valueIds  for "select" attributes (one or more)
     */
    public function __construct(
        public int $attributeId,
        public array $valueIds = [],
        public ?string $text = null,
        public ?float $number = null,
        public ?bool $boolean = null,
    ) {}
}
