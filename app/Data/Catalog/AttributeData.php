<?php

declare(strict_types=1);

namespace App\Data\Catalog;

use App\Enums\AttributeType;

final readonly class AttributeData
{
    /**
     * @param  list<ValueData>  $values  in display order; only for "select" attributes
     */
    public function __construct(
        public string $name,
        public string $slug,
        public AttributeType $type,
        public ?string $unit,
        public bool $isFilterable,
        public array $values,
    ) {}
}
