<?php

declare(strict_types=1);

namespace App\Data\Catalog;

/**
 * One value of an attribute or option as sent by the form: an existing value has an id.
 */
final readonly class ValueData
{
    public function __construct(
        public ?int $id,
        public string $value,
        public string $slug,
        public ?string $colorHex = null,
    ) {}
}
