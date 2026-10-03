<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The traffic-light rating of one SEO or readability check.
 */
enum SeoRating: string
{
    case Good = 'good';
    case Ok = 'ok';
    case Bad = 'bad';

    /**
     * Points towards the score: good 1, ok 0.5, bad 0.
     */
    public function points(): float
    {
        return match ($this) {
            self::Good => 1.0,
            self::Ok => 0.5,
            self::Bad => 0.0,
        };
    }
}
