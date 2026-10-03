<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    /** Visible on storefronts from `published_at` (a future date means scheduled). */
    case Published = 'published';
    /** No longer sold or shown, but kept (orders point at it). */
    case Archived = 'archived';
}
