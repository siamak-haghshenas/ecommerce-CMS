<?php

declare(strict_types=1);

namespace App\Enums;

enum StockMovementReason: string
{
    /** The quantity a new variant starts with. */
    case Initial = 'initial';
    /** Changed by staff in the admin panel. */
    case Adjustment = 'adjustment';
    /** Sold (orders module). */
    case Order = 'order';
    /** An order was cancelled and its items returned to stock. */
    case OrderCancelled = 'order_cancelled';
    /** A customer returned items. */
    case Return = 'return';
}
