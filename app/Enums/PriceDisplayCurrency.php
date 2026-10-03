<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How amounts are shown. They are always stored in Rials; 1 Toman = 10 Rials.
 */
enum PriceDisplayCurrency: string
{
    case Toman = 'IRT';
    case Rial = 'IRR';

    /**
     * Rials per displayed unit.
     */
    public function rialsPerUnit(): int
    {
        return $this === self::Toman ? 10 : 1;
    }
}
