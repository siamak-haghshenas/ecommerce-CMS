<?php

declare(strict_types=1);

namespace App\Data\Ai;

final readonly class AiResult
{
    /**
     * @param  array<string, mixed>  $output  the structured answer
     */
    public function __construct(
        public array $output,
        public ?string $model,
        public int $inputTokens,
        public int $outputTokens,
    ) {}
}
