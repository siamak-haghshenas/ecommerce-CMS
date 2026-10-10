<?php

declare(strict_types=1);

namespace App\Enums;

enum PromotionTargetType: string
{
    case Product = 'product';
    /** Its sub-categories are included. */
    case Category = 'category';
    case Brand = 'brand';
    case Tag = 'tag';
}
