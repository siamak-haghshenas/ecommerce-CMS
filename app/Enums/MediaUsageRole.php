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
    /** A staff member's picture on their author byline. */
    case Avatar = 'avatar';
    /** A sale event landing page's banner. */
    case Banner = 'banner';
    /** A page's Open Graph image (social media previews). */
    case OgImage = 'og_image';
    /** An image inside rich text (e.g. a product description). */
    case Content = 'content';
}
