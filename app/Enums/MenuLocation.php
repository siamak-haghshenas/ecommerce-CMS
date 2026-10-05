<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a menu appears on storefronts. Fixed: storefronts ask for a menu by location.
 */
enum MenuLocation: string
{
    case Header = 'header';
    case Footer = 'footer';
}
