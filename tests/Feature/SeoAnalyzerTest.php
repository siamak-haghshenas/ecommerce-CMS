<?php

declare(strict_types=1);

use App\Data\Seo\SeoAnalysis;
use App\Data\Seo\SeoCheck;
use App\Data\Seo\SeoInput;
use App\Support\Seo\SeoAnalyzer;
use App\Support\Seo\SeoText;

/**
 * The same cases run against the TypeScript analyzer (tests/js/seo-analysis.test.mjs),
 * so the live analysis in the admin panel and the stored scores always agree.
 *
 * @return array<string, mixed>
 */
function seoFixture(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__).'/Fixtures/seo-analysis.json'), true);
}

/**
 * @return array{checks: list<array<string, mixed>>, score: int|null}
 */
function seoAnalysisArray(SeoAnalysis $analysis): array
{
    return [
        'checks' => array_map(fn (SeoCheck $check): array => [
            'id' => $check->id,
            'rating' => $check->rating->value,
            'code' => $check->code,
            'params' => $check->params,
        ], $analysis->checks),
        'score' => $analysis->score,
    ];
}

it('rates the SEO cases like the admin panel does', function (array $input, array $expected) {
    $analysis = (new SeoAnalyzer)->analyzeSeo(new SeoInput(...$input));

    expect(seoAnalysisArray($analysis))->toEqual($expected);
})->with(fn (): array => collect(seoFixture()['seo'])->mapWithKeys(fn (array $case): array => [
    $case['name'] => [$case['input'], $case['expected']],
])->all());

it('rates the readability cases like the admin panel does', function (string $text, array $expected) {
    expect(seoAnalysisArray((new SeoAnalyzer)->analyzeReadability($text)))->toEqual($expected);
})->with(fn (): array => collect(seoFixture()['readability'])->mapWithKeys(fn (array $case): array => [
    $case['name'] => [$case['text'], $case['expected']],
])->all());

it('slugifies like the admin panel does', function (string $input, string $expected) {
    expect(SeoText::slugify($input))->toBe($expected);
})->with(fn (): array => collect(seoFixture()['slugify'])->mapWithKeys(fn (array $case): array => [
    json_encode($case[0], JSON_UNESCAPED_UNICODE) => $case,
])->all());

it('treats keyphrases typed with different keyboards and half-spaces as the same', function () {
    expect(SeoText::sameKeyphrase('كفش‌ ورزشي', 'کفش ورزشی'))->toBeTrue()
        ->and(SeoText::sameKeyphrase('کفش', 'کفش ورزشی'))->toBeFalse()
        ->and(SeoText::sameKeyphrase('', ''))->toBeFalse();
});
