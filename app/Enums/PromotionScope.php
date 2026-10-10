<?php

declare(strict_types=1);

namespace App\Enums;

enum PromotionScope: string
{
    /** Every product (minus excluded ones). */
    case All = 'all';
    /** The chosen products, categories (sub-categories included), brands and tags. */
    case Selected = 'selected';
}
