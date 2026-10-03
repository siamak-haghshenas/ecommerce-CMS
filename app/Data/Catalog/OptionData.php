<?php

declare(strict_types=1);

namespace App\Data\Catalog;

use App\Enums\OptionDisplayType;

final readonly class OptionData
{
    /**
     * @param  list<ValueData>  $values  in display order
     */
    public function __construct(
        public string $name,
        public string $slug,
        public OptionDisplayType $displayType,
        public array $values,
    ) {}
}
