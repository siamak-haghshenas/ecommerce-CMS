<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\AiTextGenerator;
use App\Services\Ai\Drivers\ClaudeAiDriver;
use App\Services\Ai\Drivers\FakeAiDriver;
use Illuminate\Support\Manager;

/**
 * Resolves the configured AI driver. Add a provider by adding a create{Name}Driver method.
 *
 * @method AiTextGenerator driver(string|null $driver = null)
 */
final class AiManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('ai.default');
    }

    protected function createClaudeDriver(): AiTextGenerator
    {
        return new ClaudeAiDriver($this->config->get('ai.drivers.claude'));
    }

    protected function createFakeDriver(): AiTextGenerator
    {
        return new FakeAiDriver;
    }
}
