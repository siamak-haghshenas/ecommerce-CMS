<?php

declare(strict_types=1);

namespace App\Support\Seo;

use Illuminate\Support\Str;
use Normalizer;

/**
 * Text helpers of the SEO analysis. Mirrors the helpers in
 * resources/js/admin/modules/seo/analysis.ts; keep both in sync.
 */
final class SeoText
{
    private const array PERSIAN_LETTERS = [
        'آ' => 'a', 'ا' => 'a', 'أ' => 'a', 'إ' => 'e', 'ب' => 'b', 'پ' => 'p', 'ت' => 't', 'ث' => 's',
        'ج' => 'j', 'چ' => 'ch', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'z', 'ر' => 'r', 'ز' => 'z',
        'ژ' => 'zh', 'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'z', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a',
        'غ' => 'gh', 'ف' => 'f', 'ق' => 'gh', 'ک' => 'k', 'ك' => 'k', 'گ' => 'g', 'ل' => 'l', 'م' => 'm',
        'ن' => 'n', 'ة' => 'e', 'ئ' => 'y', 'ء' => '',
    ];

    private const array YEH = ['ی', 'ي', 'ى'];

    private const array ALEF = ['ا', 'آ'];

    private const string PERSIAN_LETTER = '/[\x{0621}-\x{064A}\x{067E}\x{0686}\x{0698}\x{06A9}\x{06AF}\x{06CC}]/u';

    private const string PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹';

    private const string ARABIC_DIGITS = '٠١٢٣٤٥٦٧٨٩';

    /**
     * Lowercase, Persian-normalized text: ي/ك → ی/ک, Latin digits, no diacritics, half-space → space.
     */
    public static function normalize(string $text): string
    {
        $text = self::nfc($text);
        $text = str_replace(['ي', 'ى', 'ك'], ['ی', 'ی', 'ک'], $text);
        $text = strtr($text, self::digitMap());
        $text = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);
        $text = str_replace("\u{200C}", ' ', $text);

        return mb_strtolower($text);
    }

    /**
     * @return list<string>
     */
    public static function words(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', self::normalize($text), $matches);

        return $matches[0];
    }

    /**
     * Start indexes of the phrase (as whole words) in the text's words.
     *
     * @param  list<string>  $haystack
     * @param  list<string>  $phrase
     * @return list<int>
     */
    public static function occurrences(array $haystack, array $phrase): array
    {
        $found = [];
        $length = count($phrase);

        if ($length === 0) {
            return $found;
        }

        for ($index = 0; $index + $length <= count($haystack); $index++) {
            if (array_slice($haystack, $index, $length) === $phrase) {
                $found[] = $index;
                $index += $length - 1;
            }
        }

        return $found;
    }

    /**
     * @return list<list<string>>
     */
    public static function sentences(string $text): array
    {
        return self::wordGroups((array) preg_split('/[.!?؟…\n]+/u', $text));
    }

    /**
     * @return list<list<string>>
     */
    public static function paragraphs(string $text): array
    {
        return self::wordGroups((array) preg_split('/\n+/u', $text));
    }

    /**
     * Trimmed length in characters (Unicode code points, as in the browser).
     */
    public static function length(string $text): int
    {
        return mb_strlen(self::trim($text));
    }

    public static function trim(string $text): string
    {
        return (string) preg_replace('/^[\s\p{Z}\x{FEFF}]+|[\s\p{Z}\x{FEFF}]+$/u', '', $text);
    }

    /**
     * Whether two keyphrases are the same once normalized.
     */
    public static function sameKeyphrase(string $first, string $second): bool
    {
        $normalized = implode(' ', self::words($first));

        return $normalized !== '' && $normalized === implode(' ', self::words($second));
    }

    /**
     * A Latin slug from any text; Persian is transliterated ("Finglish").
     */
    public static function slugify(string $text): string
    {
        $latin = mb_strtolower(self::transliteratePersian($text));
        $latin = class_exists(Normalizer::class)
            ? (string) preg_replace('/[\x{0300}-\x{036f}]/u', '', (string) Normalizer::normalize($latin, Normalizer::NFKD))
            : Str::ascii($latin);
        $latin = (string) preg_replace('/[^a-z0-9]+/', '-', $latin);

        return trim($latin, '-');
    }

    /**
     * Persian leaves most vowels unwritten, so this is only a starting point:
     * «پیراهن مردانه» → "pirahn mrdane". و, ی and ه depend on their position in the word.
     */
    private static function transliteratePersian(string $text): string
    {
        $chars = mb_str_split(self::nfc($text));
        $isLetter = fn (string $char): bool => $char !== '' && preg_match(self::PERSIAN_LETTER, $char) === 1;
        $result = '';

        foreach ($chars as $index => $char) {
            $previous = $chars[$index - 1] ?? '';
            $next = $chars[$index + 1] ?? '';
            $startsWord = ! $isLetter($previous);
            $endsWord = ! $isLetter($next);
            $isYeh = in_array($char, self::YEH, true);

            $result .= match (true) {
                // The half-space ends a word part, e.g. «دسته‌بندی».
                $char === "\u{200C}" => '',
                // A word-initial alef carrying a vowel: «ایران» → "iran", «اوستا» → "osta".
                $char === 'ا' && $startsWord && (in_array($next, self::YEH, true) || $next === 'و') => in_array($next, self::YEH, true) ? 'i' : 'o',
                $previous === 'ا' && ! $isLetter($chars[$index - 2] ?? '') && ($isYeh || $char === 'و') => '',
                $char === 'و' => $startsWord || in_array($previous, self::ALEF, true) || in_array($next, self::ALEF, true) ? 'v' : 'u',
                $isYeh => $startsWord || in_array($previous, self::ALEF, true) || in_array($next, self::ALEF, true) ? 'y' : 'i',
                $char === 'ه' || $char === 'ۀ' => $endsWord && ! $startsWord ? 'e' : 'h',
                default => self::digitMap()[$char] ?? self::PERSIAN_LETTERS[$char] ?? $char,
            };
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $parts
     * @return list<list<string>>
     */
    private static function wordGroups(array $parts): array
    {
        return array_values(array_filter(
            array_map(fn (string $part): array => self::words($part), $parts),
            fn (array $words): bool => $words !== [],
        ));
    }

    /**
     * @return array<string, string>
     */
    private static function digitMap(): array
    {
        $map = [];

        foreach ([self::PERSIAN_DIGITS, self::ARABIC_DIGITS] as $digits) {
            foreach (mb_str_split($digits) as $value => $digit) {
                $map[$digit] = (string) $value;
            }
        }

        return $map;
    }

    private static function nfc(string $text): string
    {
        return class_exists(Normalizer::class) ? (string) Normalizer::normalize($text, Normalizer::NFC) : $text;
    }
}
