<?php

declare(strict_types=1);

namespace App\Services\Ai\Drivers;

use App\Contracts\AiTextGenerator;
use App\Data\Ai\AiPrompt;
use App\Data\Ai\AiResult;
use App\Exceptions\AiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Claude through the Anthropic Messages API. Structured output uses a forced tool call:
 * the model must "call" a tool whose input schema is the requested JSON schema.
 *
 * @see https://docs.anthropic.com/en/api/messages
 */
final readonly class ClaudeAiDriver implements AiTextGenerator
{
    /** Overloaded or failing: retried once, then reported as unavailable. */
    private const array TRANSIENT_STATUSES = [429, 500, 502, 503, 504, 529];

    /**
     * @param  array{base_url: string, api_key: ?string, model: string, version: string, max_tokens: int, connect_timeout: int, timeout: int}  $config
     */
    public function __construct(private array $config) {}

    public function model(): string
    {
        return $this->config['model'];
    }

    public function generate(AiPrompt $prompt): AiResult
    {
        if (blank($this->config['api_key'])) {
            throw AiException::notConfigured('ANTHROPIC_API_KEY is not set');
        }

        try {
            $response = Http::baseUrl($this->config['base_url'])
                ->withHeaders([
                    'x-api-key' => $this->config['api_key'],
                    'anthropic-version' => $this->config['version'],
                ])
                ->acceptJson()
                ->connectTimeout($this->config['connect_timeout'])
                ->timeout($this->config['timeout'])
                ->retry(2, 1000, fn (\Throwable $exception): bool => $this->isTransient($exception))
                ->post('/v1/messages', [
                    'model' => $this->config['model'],
                    'max_tokens' => $this->config['max_tokens'],
                    'system' => $prompt->system,
                    'messages' => [['role' => 'user', 'content' => $prompt->message]],
                    'tools' => [[
                        'name' => $prompt->outputName,
                        'description' => $prompt->outputDescription,
                        'input_schema' => $prompt->outputSchema,
                    ]],
                    'tool_choice' => ['type' => 'tool', 'name' => $prompt->outputName],
                ]);
        } catch (ConnectionException $exception) {
            throw AiException::unavailable($exception->getMessage());
        } catch (RequestException $exception) {
            $status = $exception->response->status();

            throw in_array($status, [401, 403], true)
                ? AiException::notConfigured("Anthropic rejected the API key ({$status})")
                : AiException::unavailable("Anthropic responded {$status}: ".mb_substr($exception->response->body(), 0, 500));
        }

        /** @var array{model?: string, content?: list<array{type?: string, name?: string, input?: mixed}>, usage?: array{input_tokens?: int, output_tokens?: int}} $body */
        $body = (array) $response->json();
        $output = collect($body['content'] ?? [])
            ->first(fn (array $block): bool => ($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === $prompt->outputName);

        if (! is_array($output) || ! is_array($output['input'] ?? null)) {
            throw AiException::invalidResponse('No structured answer in the response');
        }

        return new AiResult(
            output: $output['input'],
            model: $body['model'] ?? $this->config['model'],
            inputTokens: (int) ($body['usage']['input_tokens'] ?? 0),
            outputTokens: (int) ($body['usage']['output_tokens'] ?? 0),
        );
    }

    private function isTransient(\Throwable $exception): bool
    {
        return $exception instanceof ConnectionException
            || ($exception instanceof RequestException && in_array($exception->response->status(), self::TRANSIENT_STATUSES, true));
    }
}
