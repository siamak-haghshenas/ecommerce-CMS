<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\Ai\AiPrompt;
use App\Data\Ai\AiResult;
use App\Exceptions\AiException;

interface AiTextGenerator
{
    /**
     * Ask the model for structured output matching the prompt's JSON schema.
     *
     * @throws AiException when the provider is not configured, unreachable, or answers in the wrong shape
     */
    public function generate(AiPrompt $prompt): AiResult;

    /**
     * The model that answers, for usage records.
     */
    public function model(): string;
}
