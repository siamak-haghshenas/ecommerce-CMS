<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which discount gives a variant its current price (the lowest one wins; they never stack).
 */
enum DiscountSource: string
{
    /** The variant's own sale price. */
    case Sale = 'sale';
    case Promotion = 'promotion';
    /** The sale event chosen for the variant (the highest-priority one running for it). */
    case SaleEvent = 'sale_event';
}
