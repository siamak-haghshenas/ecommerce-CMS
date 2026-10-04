<?php

declare(strict_types=1);

namespace App\Enums;

enum PageStatus: string
{
    case Draft = 'draft';
    /** Visible on storefronts from `published_at` (a future date means scheduled). */
    case Published = 'published';
}
