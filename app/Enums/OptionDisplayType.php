<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How storefronts show an option's values.
 */
enum OptionDisplayType: string
{
    /** Buttons with the value text (sizes). */
    case Text = 'text';
    /** Color swatches; every value needs a color_hex. */
    case Color = 'color';
}
