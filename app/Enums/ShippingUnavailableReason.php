<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a shipping method isn't offered for an order. When several apply, the first in this list
 * is given.
 */
enum ShippingUnavailableReason: string
{
    case Inactive = 'inactive';

    /** A courier method that doesn't serve the address's city. */
    case CityNotServed = 'city_not_served';

    case BelowMinOrder = 'below_min_order';

    /** A product has no weight and the store has no default weight. */
    case MissingWeight = 'missing_weight';

    /** A product is missing a dimension (volume methods have no default). */
    case MissingDimensions = 'missing_dimensions';

    /** Heavier or larger than the last range with an upper limit. */
    case OutOfRange = 'out_of_range';
}
