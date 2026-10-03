<?php

declare(strict_types=1);

namespace App\Services\Ai\Drivers;

use App\Contracts\AiTextGenerator;
use App\Data\Ai\AiPrompt;
use App\Data\Ai\AiResult;
use App\Exceptions\AiException;

/**
 * Canned answers for tests: set the next answer (or failure) and inspect the prompts sent.
 */
final class FakeAiDriver implements AiTextGenerator
{
    /** @var array<string, mixed> */
    private array $output = [];

    private ?AiException $failure = null;

    /** @var list<AiPrompt> */
    private array $prompts = [];

    /**
     * @param  array<string, mixed>  $output
     */
    public function respondWith(array $output): self
    {
        $this->output = $output;
        $this->failure = null;

        return $this;
    }

    public function failWith(AiException $failure): self
    {
        $this->failure = $failure;

        return $this;
    }

    /**
     * @return list<AiPrompt>
     */
    public function prompts(): array
    {
        return $this->prompts;
    }

    public function model(): string
    {
        return 'fake-model';
    }

    public function generate(AiPrompt $prompt): AiResult
    {
        $this->prompts[] = $prompt;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new AiResult($this->output, 'fake-model', inputTokens: 120, outputTokens: 80);
    }
}
