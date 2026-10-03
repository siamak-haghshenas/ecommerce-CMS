<?php

declare(strict_types=1);

namespace App\Data\Ai;

final readonly class AiPrompt
{
    /**
     * @param  string  $outputName  a short snake_case name for the structured answer
     * @param  array<string, mixed>  $outputSchema  JSON schema the answer must match
     */
    public function __construct(
        public string $system,
        public string $message,
        public string $outputName,
        public string $outputDescription,
        public array $outputSchema,
    ) {}
}
