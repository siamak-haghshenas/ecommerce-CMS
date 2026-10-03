<?php

declare(strict_types=1);

namespace App\Actions\Seo;

use App\Contracts\AiTextGenerator;
use App\Data\Ai\AiPrompt;
use App\Data\Ai\AiResult;
use App\Data\Seo\SeoSuggestionContext;
use App\Data\Seo\SeoSuggestions;
use App\Exceptions\AiException;
use App\Models\AiRequest;
use App\Models\User;
use App\Support\Seo\SeoAnalyzer;

final readonly class SuggestSeoAction
{
    private const int OPTIONS = 3;

    public function __construct(private AiTextGenerator $ai) {}

    /**
     * Ask AI for three focus keyphrases, SEO titles and meta descriptions. Nothing is saved:
     * the admin picks or edits a suggestion. Every call is recorded in ai_requests.
     *
     * @throws AiException
     */
    public function handle(User $actor, SeoSuggestionContext $context): SeoSuggestions
    {
        $result = null;

        try {
            $result = $this->ai->generate($this->prompt($context));
            $suggestions = $this->suggestionsFrom($result);
        } catch (AiException $exception) {
            $this->record($actor, $context, $result, $exception);

            throw $exception;
        }

        $this->record($actor, $context, $result, null);

        return $suggestions;
    }

    private function prompt(SeoSuggestionContext $context): AiPrompt
    {
        // Persian when the name (or its alternate spelling, e.g. a brand's Persian name) is Persian.
        $persian = preg_match('/\p{Arabic}/u', $context->name.' '.$context->alternateName) === 1;
        $language = $persian ? 'Persian (Farsi), using Persian punctuation and the half-space (ZWNJ) where standard' : 'English';
        $page = $context->type->pageDescription();

        $details = array_filter([
            'Name' => $context->name,
            'Also written as' => $context->alternateName,
            'Parent categories (top first)' => $context->parentPath === [] ? null : implode(' > ', $context->parentPath),
            'Description' => $context->description,
            "Admin's focus keyphrase" => $context->focusKeyphrase,
        ]);

        $message = "Write SEO suggestions for {$page}.\n\n"
            .collect($details)->map(fn (string $value, string $label): string => "{$label}: {$value}")->implode("\n");

        $titleRange = SeoAnalyzer::TITLE_MIN.'–'.SeoAnalyzer::TITLE_MAX;
        $descriptionRange = SeoAnalyzer::META_DESCRIPTION_MIN.'–'.SeoAnalyzer::META_DESCRIPTION_MAX;
        $options = self::OPTIONS;

        $system = <<<PROMPT
            You are an SEO copywriter for an online store. You write metadata for {$page}.
            Write everything in {$language}.

            Return exactly {$options} options for each field:
            - keyphrases: 1–4 word search terms shoppers really type to find this page. If the admin gave a focus keyphrase, the first option is that keyphrase (cleaned up if needed).
            - titles: {$titleRange} characters, starting with or very close to the focus keyphrase (the admin's, else your first keyphrase), clear and appealing.
            - meta_descriptions: {$descriptionRange} characters, containing the same keyphrase naturally, describing what shoppers find and ending with a soft call to action.

            Rules: use only facts from the given details; never invent prices, discounts, delivery times, brands or guarantees. No quotation marks, emoji, hashtags or ALL CAPS. Make the three options of each field meaningfully different.
            PROMPT;

        $list = fn (string $description): array => [
            'type' => 'array',
            'description' => $description,
            'items' => ['type' => 'string'],
            'minItems' => $options,
            'maxItems' => $options,
        ];

        return new AiPrompt(
            system: $system,
            message: $message,
            outputName: 'seo_suggestions',
            outputDescription: 'Return the SEO suggestions for the page.',
            outputSchema: [
                'type' => 'object',
                'properties' => [
                    'keyphrases' => $list('Focus keyphrase options'),
                    'titles' => $list('SEO title options'),
                    'meta_descriptions' => $list('Meta description options'),
                ],
                'required' => ['keyphrases', 'titles', 'meta_descriptions'],
            ],
        );
    }

    /**
     * @throws AiException when a field has no usable option
     */
    private function suggestionsFrom(AiResult $result): SeoSuggestions
    {
        $clean = function (string $field) use ($result): array {
            $options = collect(is_array($result->output[$field] ?? null) ? $result->output[$field] : [])
                ->filter(fn (mixed $option): bool => is_string($option))
                ->map(fn (string $option): string => trim($option, " \t\n\r\0\x0B\"'«»“”"))
                ->filter(fn (string $option): bool => $option !== '')
                ->unique()
                ->take(self::OPTIONS)
                ->values()
                ->all();

            if ($options === []) {
                throw AiException::invalidResponse("No usable {$field}");
            }

            return $options;
        };

        return new SeoSuggestions(
            keyphrases: $clean('keyphrases'),
            titles: $clean('titles'),
            metaDescriptions: $clean('meta_descriptions'),
        );
    }

    private function record(User $actor, SeoSuggestionContext $context, ?AiResult $result, ?AiException $failure): void
    {
        AiRequest::query()->create([
            'user_id' => $actor->id,
            'feature' => "{$context->type->value}_seo",
            'subject_type' => $context->subject?->getMorphClass(),
            'subject_id' => $context->subject?->getKey(),
            'driver' => (string) config('ai.default'),
            'model' => $result->model ?? $this->ai->model(),
            'input_tokens' => $result->inputTokens ?? 0,
            'output_tokens' => $result->outputTokens ?? 0,
            'status' => $failure === null ? AiRequest::STATUS_SUCCEEDED : AiRequest::STATUS_FAILED,
            'error_code' => $failure?->errorCode,
        ]);
    }
}
