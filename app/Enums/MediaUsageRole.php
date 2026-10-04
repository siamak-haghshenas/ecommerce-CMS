<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where on an item a library file is used ("where it's used" lists these).
 */
enum MediaUsageRole: string
{
    /** A product's gallery (ordered by position). */
    case Gallery = 'gallery';
    /** A variant's own picture. */
    case VariantImage = 'variant_image';
    /** A category's image. */
    case Image = 'image';
    /** A brand's logo. */
    case Logo = 'logo';
    /** An image inside rich text (e.g. a product description). */
    case Content = 'content';
}
