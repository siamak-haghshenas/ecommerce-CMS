<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Something the customer should know about a cart line. A line is never changed or removed
 * silently; the blocking problems keep the cart from checkout until the customer fixes them.
 */
enum CartLineProblem: string
{
    /** The product is hidden, deleted or in no visible category, or the variant is disabled or deleted. */
    case Unavailable = 'unavailable';
    /** Stock is tracked, backorders are off and nothing is left. */
    case OutOfStock = 'out_of_stock';
    /** The quantity is above the line's limit, or a merge cut it to the limit (`limited_from`). */
    case ExceedsLimit = 'exceeds_limit';
    /** The price is not the one the customer last saw (a notice). */
    case PriceChanged = 'price_changed';
}
