<?php

declare(strict_types=1);

namespace App\Enums;

enum AttributeType: string
{
    case Text = 'text';
    /** A number, with an optional unit (GB, inch). Filterable as a range. */
    case Number = 'number';
    case Boolean = 'boolean';
    /** One or more of the attribute's predefined values. */
    case Select = 'select';

    public function hasValues(): bool
    {
        return $this === self::Select;
    }
}
