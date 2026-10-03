<?php

declare(strict_types=1);

namespace App\Data\Seo;

use App\Enums\SeoRating;

final readonly class SeoCheck
{
    /**
     * @param  string  $code  the message variant shown by the admin panel (admin.seo.checks.<id>.<code>)
     * @param  array<string, int|float|string>  $params
     */
    public function __construct(
        public string $id,
        public SeoRating $rating,
        public string $code,
        public array $params = [],
    ) {}
}
