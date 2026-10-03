<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductType: string
{
    /** One SKU: a single variant without option values. */
    case Simple = 'simple';
    /** Variants generated from option value combinations (Size × Color). */
    case Variable = 'variable';
}
