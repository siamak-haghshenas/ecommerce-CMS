<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a shipping method's fee is worked out. Each method has exactly one.
 */
enum ShippingMethodType: string
{
    /** Ranges of the order's weight (grams). */
    case Weight = 'weight';

    /** Ranges of the order's volume (cubic centimetres, from the products' dimensions). */
    case Volume = 'volume';

    /** Chosen cities, each with the default fee or one of its own. */
    case Courier = 'courier';

    public function hasRanges(): bool
    {
        return $this !== self::Courier;
    }
}
