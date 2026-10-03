<?php

declare(strict_types=1);

namespace App\Data\Seo;

final readonly class SeoAnalysis
{
    /**
     * @param  list<SeoCheck>  $checks
     * @param  int|null  $score  0–100, or null when there was nothing to analyse
     */
    public function __construct(
        public array $checks,
        public ?int $score,
    ) {}

    /**
     * @param  list<SeoCheck>  $checks
     */
    public static function fromChecks(array $checks): self
    {
        if ($checks === []) {
            return new self([], null);
        }

        $points = array_sum(array_map(fn (SeoCheck $check): float => $check->rating->points(), $checks));

        return new self($checks, (int) round($points / count($checks) * 100));
    }
}
