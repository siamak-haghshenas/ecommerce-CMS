<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Data\Seo\SeoAnalysis;
use App\Data\Seo\SeoCheck;
use App\Data\Seo\SeoInput;
use App\Enums\SeoRating;

/**
 * SEO and readability analysis. The admin panel runs the same rules live
 * (resources/js/admin/modules/seo/analysis.ts); the server re-runs them on save to
 * store the scores. Both run the cases in tests/Fixtures/seo-analysis.json: change a rule in
 * both and add a case there.
 */
final class SeoAnalyzer
{
    public const int TITLE_MIN = 30;

    public const int TITLE_MAX = 60;

    public const int META_DESCRIPTION_MIN = 120;

    public const int META_DESCRIPTION_MAX = 160;

    public const int DESCRIPTION_MIN_WORDS = 50;

    private const int MAX_KEYPHRASE_DENSITY = 3;

    private const int LONG_SENTENCE_WORDS = 20;

    private const int LONG_PARAGRAPH_OK_WORDS = 100;

    private const int LONG_PARAGRAPH_BAD_WORDS = 150;

    private const array TRANSITION_WORDS = [
        // Persian
        'اما', 'ولی', 'بنابراین', 'در نتیجه', 'همچنین', 'علاوه بر این', 'به عنوان مثال', 'برای مثال',
        'در ضمن', 'سپس', 'در نهایت', 'زیرا', 'چون', 'از این رو', 'به همین دلیل', 'با این حال',
        'در حالی که', 'به طور کلی', 'پس', 'اگرچه',
        // English
        'however', 'therefore', 'because', 'also', 'for example', 'for instance', 'in addition', 'moreover',
        'finally', 'then', 'although', 'as a result', 'in conclusion', 'furthermore', 'meanwhile',
        'consequently', 'thus', 'so',
    ];

    public function analyzeSeo(SeoInput $input): SeoAnalysis
    {
        $keyphrase = SeoText::words($input->focusKeyphrase);
        $title = SeoText::trim($input->metaTitle) !== '' ? $input->metaTitle : $input->name;
        $descriptionWords = SeoText::words($input->description);
        $checks = [];

        if ($keyphrase === []) {
            $checks[] = new SeoCheck('keyphrase_set', SeoRating::Bad, 'missing');
        } else {
            $checks[] = new SeoCheck('keyphrase_set', SeoRating::Good, 'good');
            array_push($checks, ...$this->keyphraseChecks($input, $keyphrase, $title, $descriptionWords));
        }

        $titleLength = SeoText::length($input->metaTitle);
        $checks[] = match (true) {
            $titleLength === 0 => new SeoCheck('title_length', SeoRating::Ok, 'empty'),
            $titleLength < self::TITLE_MIN => new SeoCheck('title_length', SeoRating::Ok, 'short', ['length' => $titleLength]),
            $titleLength > self::TITLE_MAX => new SeoCheck('title_length', SeoRating::Bad, 'long', ['length' => $titleLength]),
            default => new SeoCheck('title_length', SeoRating::Good, 'good', ['length' => $titleLength]),
        };

        $metaLength = SeoText::length($input->metaDescription);
        $checks[] = match (true) {
            $metaLength === 0 => new SeoCheck('meta_description_length', SeoRating::Bad, 'empty'),
            $metaLength < self::META_DESCRIPTION_MIN => new SeoCheck('meta_description_length', SeoRating::Ok, 'short', ['length' => $metaLength]),
            $metaLength > self::META_DESCRIPTION_MAX => new SeoCheck('meta_description_length', SeoRating::Ok, 'long', ['length' => $metaLength]),
            default => new SeoCheck('meta_description_length', SeoRating::Good, 'good', ['length' => $metaLength]),
        };

        $wordCount = count($descriptionWords);
        $checks[] = match (true) {
            $wordCount === 0 => new SeoCheck('description_length', SeoRating::Bad, 'empty'),
            $wordCount < self::DESCRIPTION_MIN_WORDS => new SeoCheck('description_length', SeoRating::Ok, 'short', ['words' => $wordCount]),
            default => new SeoCheck('description_length', SeoRating::Good, 'good', ['words' => $wordCount]),
        };

        $checks[] = $input->hasImage
            ? new SeoCheck('image', SeoRating::Good, 'good')
            : new SeoCheck('image', SeoRating::Ok, 'missing');

        return SeoAnalysis::fromChecks($checks);
    }

    /**
     * Readability of the description; no checks (and no score) until it has enough words.
     */
    public function analyzeReadability(string $text): SeoAnalysis
    {
        if (count(SeoText::words($text)) < self::DESCRIPTION_MIN_WORDS) {
            return SeoAnalysis::fromChecks([]);
        }

        $sentences = SeoText::sentences($text);
        $sentenceCount = count($sentences);
        $checks = [];

        $long = count(array_filter($sentences, fn (array $sentence): bool => count($sentence) > self::LONG_SENTENCE_WORDS));
        $longPercent = (int) round($long / $sentenceCount * 100);
        $checks[] = $longPercent <= 25
            ? new SeoCheck('sentence_length', SeoRating::Good, 'good')
            : new SeoCheck('sentence_length', $longPercent <= 30 ? SeoRating::Ok : SeoRating::Bad, 'long', ['percent' => $longPercent]);

        $longestParagraph = max(array_map(count(...), SeoText::paragraphs($text)));
        $checks[] = match (true) {
            $longestParagraph > self::LONG_PARAGRAPH_BAD_WORDS => new SeoCheck('paragraph_length', SeoRating::Bad, 'long', ['words' => $longestParagraph]),
            $longestParagraph > self::LONG_PARAGRAPH_OK_WORDS => new SeoCheck('paragraph_length', SeoRating::Ok, 'long', ['words' => $longestParagraph]),
            default => new SeoCheck('paragraph_length', SeoRating::Good, 'good'),
        };

        $transitions = array_map(SeoText::words(...), self::TRANSITION_WORDS);
        $withTransition = count(array_filter($sentences, function (array $sentence) use ($transitions): bool {
            foreach ($transitions as $phrase) {
                if (SeoText::occurrences($sentence, $phrase) !== []) {
                    return true;
                }
            }

            return false;
        }));
        $transitionPercent = (int) round($withTransition / $sentenceCount * 100);
        $checks[] = $transitionPercent >= 30
            ? new SeoCheck('transition_words', SeoRating::Good, 'good', ['percent' => $transitionPercent])
            : new SeoCheck('transition_words', $transitionPercent >= 20 ? SeoRating::Ok : SeoRating::Bad, 'few', ['percent' => $transitionPercent]);

        $longestRun = 1;
        $run = 1;

        foreach ($sentences as $index => $sentence) {
            $run = $index > 0 && $sentence[0] === $sentences[$index - 1][0] ? $run + 1 : 1;
            $longestRun = max($longestRun, $run);
        }

        $checks[] = $longestRun >= 3
            ? new SeoCheck('consecutive_sentences', SeoRating::Bad, 'repeated', ['count' => $longestRun])
            : new SeoCheck('consecutive_sentences', SeoRating::Good, 'good');

        return SeoAnalysis::fromChecks($checks);
    }

    /**
     * @param  list<string>  $keyphrase
     * @param  list<string>  $descriptionWords
     * @return list<SeoCheck>
     */
    private function keyphraseChecks(SeoInput $input, array $keyphrase, string $title, array $descriptionWords): array
    {
        $titleWords = SeoText::words($title);
        $inTitle = SeoText::occurrences($titleWords, $keyphrase);

        $slugParts = array_values(array_filter(explode('-', $input->slug), fn (string $part): bool => $part !== ''));
        $keyphraseParts = array_values(array_filter(explode('-', SeoText::slugify($input->focusKeyphrase)), fn (string $part): bool => $part !== ''));
        $matched = count(array_filter($keyphraseParts, fn (string $part): bool => in_array($part, $slugParts, true)));

        $count = count(SeoText::occurrences($descriptionWords, $keyphrase));
        $density = $descriptionWords === [] ? 0 : round($count * count($keyphrase) / count($descriptionWords) * 1000) / 10;

        return [
            match (true) {
                $inTitle === [] => new SeoCheck('keyphrase_in_title', SeoRating::Bad, 'missing'),
                count($titleWords) > $inTitle[0] * 2 => new SeoCheck('keyphrase_in_title', SeoRating::Good, 'good'),
                default => new SeoCheck('keyphrase_in_title', SeoRating::Ok, 'late'),
            },
            SeoText::occurrences(SeoText::words($input->metaDescription), $keyphrase) !== []
                ? new SeoCheck('keyphrase_in_meta_description', SeoRating::Good, 'good')
                : new SeoCheck('keyphrase_in_meta_description', SeoRating::Bad, 'missing'),
            match (true) {
                $keyphraseParts !== [] && $matched === count($keyphraseParts) => new SeoCheck('keyphrase_in_slug', SeoRating::Good, 'good'),
                $matched > 0 => new SeoCheck('keyphrase_in_slug', SeoRating::Ok, 'partial'),
                default => new SeoCheck('keyphrase_in_slug', SeoRating::Bad, 'missing'),
            },
            match (true) {
                $count === 0 => new SeoCheck('keyphrase_in_description', SeoRating::Bad, 'missing'),
                count($descriptionWords) >= self::DESCRIPTION_MIN_WORDS && $density > self::MAX_KEYPHRASE_DENSITY => new SeoCheck('keyphrase_in_description', SeoRating::Ok, 'too_often', ['count' => $count, 'density' => $density]),
                default => new SeoCheck('keyphrase_in_description', SeoRating::Good, 'good', ['count' => $count]),
            },
            $input->keyphraseUsedBy !== []
                ? new SeoCheck('keyphrase_unique', SeoRating::Bad, 'used', ['names' => implode('، ', $input->keyphraseUsedBy)])
                : new SeoCheck('keyphrase_unique', SeoRating::Good, 'good'),
        ];
    }
}
