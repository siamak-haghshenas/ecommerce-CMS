<?php

declare(strict_types=1);

namespace App\Enums;

enum PromotionType: string
{
    /** `value` is a percentage (1–90) of the regular price. */
    case Percent = 'percent';
    /** `value` is an amount in Rials taken off each unit. */
    case Fixed = 'fixed';
}
