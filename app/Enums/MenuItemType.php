<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a menu item links to.
 */
enum MenuItemType: string
{
    case Page = 'page';
    case Category = 'category';
    /** An address typed by the admin: absolute http/https, or a storefront path starting with "/". */
    case Url = 'url';
}
