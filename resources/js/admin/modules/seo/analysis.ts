/**
 * SEO and readability analysis for categories.
 *
 * Mirrors App\Support\Seo\SeoAnalyzer on the server, which stores the scores on save. Both run the
 * shared cases in tests/Fixtures/seo-analysis.json: change a rule in both and add a case there.
 * Self-contained (no imports) so `npm test` can run it with Node directly.
 */

export type SeoRating = 'good' | 'ok' | 'bad';

export interface SeoCheck {
    id: string;
    rating: SeoRating;
    /** The message to show: admin.seo.checks.<id>.<code>. */
    code: string;
    params: Record<string, number | string>;
}

export interface SeoAnalysis {
    checks: SeoCheck[];
    /** 0–100, or null when there is nothing to analyse (readability of a short text). */
    score: number | null;
}

export interface SeoInput {
    name: string;
    slug: string;
    description: string;
    metaTitle: string;
    metaDescription: string;
    focusKeyphrase: string;
    hasImage: boolean;
    /** Names of other categories using the same keyphrase. */
    keyphraseUsedBy: string[];
}

export const TITLE_MIN = 30;
export const TITLE_MAX = 60;
export const META_DESCRIPTION_MIN = 120;
export const META_DESCRIPTION_MAX = 160;
export const DESCRIPTION_MIN_WORDS = 50;
const MAX_KEYPHRASE_DENSITY = 3;
const LONG_SENTENCE_WORDS = 20;
const LONG_PARAGRAPH_OK_WORDS = 100;
const LONG_PARAGRAPH_BAD_WORDS = 150;

const TRANSITION_WORDS = [
    // Persian
    'اما',
    'ولی',
    'بنابراین',
    'در نتیجه',
    'همچنین',
    'علاوه بر این',
    'به عنوان مثال',
    'برای مثال',
    'در ضمن',
    'سپس',
    'در نهایت',
    'زیرا',
    'چون',
    'از این رو',
    'به همین دلیل',
    'با این حال',
    'در حالی که',
    'به طور کلی',
    'پس',
    'اگرچه',
    // English
    'however',
    'therefore',
    'because',
    'also',
    'for example',
    'for instance',
    'in addition',
    'moreover',
    'finally',
    'then',
    'although',
    'as a result',
    'in conclusion',
    'furthermore',
    'meanwhile',
    'consequently',
    'thus',
    'so',
].map((phrase) => words(phrase));

// ---------------------------------------------------------------------------
// Text helpers
// ---------------------------------------------------------------------------

/** Lowercase, Persian-normalized text: ي/ك → ی/ک, Latin digits, no diacritics, half-space → space. */
export function normalize(text: string): string {
    return text
        .normalize('NFC')
        .replace(/ي/g, 'ی')
        .replace(/ى/g, 'ی')
        .replace(/ك/g, 'ک')
        .replace(/[۰-۹]/g, (digit) => String(digit.charCodeAt(0) - 0x06f0))
        .replace(/[٠-٩]/g, (digit) => String(digit.charCodeAt(0) - 0x0660))
        .replace(/[ً-ٰٟـ]/g, '')
        .replace(/‌/g, ' ')
        .toLowerCase();
}

export function words(text: string): string[] {
    return normalize(text).match(/[\p{L}\p{N}]+/gu) ?? [];
}

/** Start indexes of the phrase (as whole words) in the text's words. */
function occurrences(haystack: string[], phrase: string[]): number[] {
    const found: number[] = [];

    if (phrase.length === 0) {
        return found;
    }

    for (let index = 0; index + phrase.length <= haystack.length; index++) {
        if (phrase.every((word, offset) => haystack[index + offset] === word)) {
            found.push(index);
            index += phrase.length - 1;
        }
    }

    return found;
}

function sentences(text: string): string[][] {
    return text
        .split(/[.!?؟…\n]+/)
        .map((sentence) => words(sentence))
        .filter((sentence) => sentence.length > 0);
}

function paragraphs(text: string): string[][] {
    return text
        .split(/\n+/)
        .map((paragraph) => words(paragraph))
        .filter((paragraph) => paragraph.length > 0);
}

function characterCount(text: string): number {
    return [...text.trim()].length;
}

function check(id: string, rating: SeoRating, code: string, params: SeoCheck['params'] = {}): SeoCheck {
    return { id, rating, code, params };
}

export function scoreOf(checks: SeoCheck[]): number | null {
    if (checks.length === 0) {
        return null;
    }

    const points = checks.reduce((sum, item) => sum + (item.rating === 'good' ? 1 : item.rating === 'ok' ? 0.5 : 0), 0);

    return Math.round((points / checks.length) * 100);
}

/** The color of a score: ≥70 good, ≥40 ok, otherwise bad; null when not analysed. */
export function ratingOf(score: number | null): SeoRating | null {
    if (score === null) {
        return null;
    }

    return score >= 70 ? 'good' : score >= 40 ? 'ok' : 'bad';
}

/** Whether two keyphrases are the same once normalized ("پیراهن‌ مردانه" equals "پیراهن مردانه"). */
export function sameKeyphrase(first: string, second: string): boolean {
    const a = words(first).join(' ');

    return a !== '' && a === words(second).join(' ');
}

// ---------------------------------------------------------------------------
// Finglish (Persian → Latin) for slugs
// ---------------------------------------------------------------------------

const PERSIAN_LETTERS: Record<string, string> = {
    آ: 'a',
    ا: 'a',
    أ: 'a',
    إ: 'e',
    ب: 'b',
    پ: 'p',
    ت: 't',
    ث: 's',
    ج: 'j',
    چ: 'ch',
    ح: 'h',
    خ: 'kh',
    د: 'd',
    ذ: 'z',
    ر: 'r',
    ز: 'z',
    ژ: 'zh',
    س: 's',
    ش: 'sh',
    ص: 's',
    ض: 'z',
    ط: 't',
    ظ: 'z',
    ع: 'a',
    غ: 'gh',
    ف: 'f',
    ق: 'gh',
    ک: 'k',
    ك: 'k',
    گ: 'g',
    ل: 'l',
    م: 'm',
    ن: 'n',
    ة: 'e',
    ئ: 'y',
    ء: '',
};

const YEH = new Set(['ی', 'ي', 'ى']);
const ALEF = new Set(['ا', 'آ']);
const PERSIAN_LETTER = /[ء-يپچژکگی]/;

/**
 * Writes Persian text in Latin letters ("Finglish"). Persian leaves most vowels unwritten,
 * so the result is only a starting point, e.g. «پیراهن مردانه» → "pirahn mrdane".
 */
function transliteratePersian(text: string): string {
    const chars = [...text.normalize('NFC')];

    return chars
        .map((char, index) => {
            const previous = chars[index - 1] ?? '';
            const next = chars[index + 1] ?? '';
            const startsWord = !PERSIAN_LETTER.test(previous);
            const endsWord = !PERSIAN_LETTER.test(next);

            // The half-space ends a word part, e.g. «دسته‌بندی».
            if (char === '‌') {
                return '';
            }

            // A word-initial alef carrying a vowel: «ایران» → "iran", «اوستا» → "osta".
            if (char === 'ا' && startsWord && (YEH.has(next) || next === 'و')) {
                return YEH.has(next) ? 'i' : 'o';
            }

            if (previous === 'ا' && !PERSIAN_LETTER.test(chars[index - 2] ?? '') && (YEH.has(char) || char === 'و')) {
                return '';
            }

            if (char === 'و') {
                return startsWord || ALEF.has(previous) || ALEF.has(next) ? 'v' : 'u';
            }

            if (YEH.has(char)) {
                return startsWord || ALEF.has(previous) || ALEF.has(next) ? 'y' : 'i';
            }

            if (char === 'ه' || char === 'ۀ') {
                return endsWord && !startsWord ? 'e' : 'h';
            }

            const persianDigit = '۰۱۲۳۴۵۶۷۸۹'.indexOf(char);
            const arabicDigit = '٠١٢٣٤٥٦٧٨٩'.indexOf(char);

            if (persianDigit >= 0 || arabicDigit >= 0) {
                return String(Math.max(persianDigit, arabicDigit));
            }

            return PERSIAN_LETTERS[char] ?? char;
        })
        .join('');
}

/** A Latin slug from any text; Persian is transliterated. */
export function slugify(text: string): string {
    return transliteratePersian(text)
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

// ---------------------------------------------------------------------------
// SEO
// ---------------------------------------------------------------------------

export function analyzeSeo(input: SeoInput): SeoAnalysis {
    const keyphrase = words(input.focusKeyphrase);
    const title = input.metaTitle.trim() !== '' ? input.metaTitle : input.name;
    const descriptionWords = words(input.description);
    const checks: SeoCheck[] = [];

    if (keyphrase.length === 0) {
        checks.push(check('keyphrase_set', 'bad', 'missing'));
    } else {
        checks.push(check('keyphrase_set', 'good', 'good'));

        const titleWords = words(title);
        const inTitle = occurrences(titleWords, keyphrase);
        checks.push(
            inTitle.length === 0
                ? check('keyphrase_in_title', 'bad', 'missing')
                : (inTitle[0] as number) * 2 < titleWords.length
                  ? check('keyphrase_in_title', 'good', 'good')
                  : check('keyphrase_in_title', 'ok', 'late'),
        );

        checks.push(
            occurrences(words(input.metaDescription), keyphrase).length > 0
                ? check('keyphrase_in_meta_description', 'good', 'good')
                : check('keyphrase_in_meta_description', 'bad', 'missing'),
        );

        const slugParts = input.slug.split('-').filter((part) => part !== '');
        const keyphraseParts = slugify(input.focusKeyphrase)
            .split('-')
            .filter((part) => part !== '');
        const matched = keyphraseParts.filter((part) => slugParts.includes(part)).length;
        checks.push(
            keyphraseParts.length > 0 && matched === keyphraseParts.length
                ? check('keyphrase_in_slug', 'good', 'good')
                : matched > 0
                  ? check('keyphrase_in_slug', 'ok', 'partial')
                  : check('keyphrase_in_slug', 'bad', 'missing'),
        );

        const count = occurrences(descriptionWords, keyphrase).length;
        const density =
            descriptionWords.length === 0
                ? 0
                : Math.round(((count * keyphrase.length) / descriptionWords.length) * 1000) / 10;
        checks.push(
            count === 0
                ? check('keyphrase_in_description', 'bad', 'missing')
                : descriptionWords.length >= DESCRIPTION_MIN_WORDS && density > MAX_KEYPHRASE_DENSITY
                  ? check('keyphrase_in_description', 'ok', 'too_often', { count, density })
                  : check('keyphrase_in_description', 'good', 'good', { count }),
        );

        checks.push(
            input.keyphraseUsedBy.length > 0
                ? check('keyphrase_unique', 'bad', 'used', { names: input.keyphraseUsedBy.join('، ') })
                : check('keyphrase_unique', 'good', 'good'),
        );
    }

    const titleLength = characterCount(input.metaTitle);
    checks.push(
        titleLength === 0
            ? check('title_length', 'ok', 'empty')
            : titleLength < TITLE_MIN
              ? check('title_length', 'ok', 'short', { length: titleLength })
              : titleLength > TITLE_MAX
                ? check('title_length', 'bad', 'long', { length: titleLength })
                : check('title_length', 'good', 'good', { length: titleLength }),
    );

    const metaLength = characterCount(input.metaDescription);
    checks.push(
        metaLength === 0
            ? check('meta_description_length', 'bad', 'empty')
            : metaLength < META_DESCRIPTION_MIN
              ? check('meta_description_length', 'ok', 'short', { length: metaLength })
              : metaLength > META_DESCRIPTION_MAX
                ? check('meta_description_length', 'ok', 'long', { length: metaLength })
                : check('meta_description_length', 'good', 'good', { length: metaLength }),
    );

    checks.push(
        descriptionWords.length === 0
            ? check('description_length', 'bad', 'empty')
            : descriptionWords.length < DESCRIPTION_MIN_WORDS
              ? check('description_length', 'ok', 'short', { words: descriptionWords.length })
              : check('description_length', 'good', 'good', { words: descriptionWords.length }),
    );

    checks.push(input.hasImage ? check('image', 'good', 'good') : check('image', 'ok', 'missing'));

    return { checks, score: scoreOf(checks) };
}

// ---------------------------------------------------------------------------
// Readability
// ---------------------------------------------------------------------------

/** Readability of the description; no checks (and no score) until it has enough words. */
export function analyzeReadability(text: string): SeoAnalysis {
    if (words(text).length < DESCRIPTION_MIN_WORDS) {
        return { checks: [], score: null };
    }

    const allSentences = sentences(text);
    const checks: SeoCheck[] = [];

    const longPercent = Math.round(
        (allSentences.filter((sentence) => sentence.length > LONG_SENTENCE_WORDS).length / allSentences.length) * 100,
    );
    checks.push(
        longPercent <= 25
            ? check('sentence_length', 'good', 'good')
            : check('sentence_length', longPercent <= 30 ? 'ok' : 'bad', 'long', { percent: longPercent }),
    );

    const longestParagraph = Math.max(...paragraphs(text).map((paragraph) => paragraph.length));
    checks.push(
        longestParagraph > LONG_PARAGRAPH_BAD_WORDS
            ? check('paragraph_length', 'bad', 'long', { words: longestParagraph })
            : longestParagraph > LONG_PARAGRAPH_OK_WORDS
              ? check('paragraph_length', 'ok', 'long', { words: longestParagraph })
              : check('paragraph_length', 'good', 'good'),
    );

    const withTransition = allSentences.filter((sentence) =>
        TRANSITION_WORDS.some((phrase) => occurrences(sentence, phrase).length > 0),
    ).length;
    const transitionPercent = Math.round((withTransition / allSentences.length) * 100);
    checks.push(
        transitionPercent >= 30
            ? check('transition_words', 'good', 'good', { percent: transitionPercent })
            : check('transition_words', transitionPercent >= 20 ? 'ok' : 'bad', 'few', {
                  percent: transitionPercent,
              }),
    );

    let longestRun = 1;
    let run = 1;
    allSentences.forEach((sentence, index) => {
        run = index > 0 && sentence[0] === allSentences[index - 1]?.[0] ? run + 1 : 1;
        longestRun = Math.max(longestRun, run);
    });
    checks.push(
        longestRun >= 3
            ? check('consecutive_sentences', 'bad', 'repeated', { count: longestRun })
            : check('consecutive_sentences', 'good', 'good'),
    );

    return { checks, score: scoreOf(checks) };
}
