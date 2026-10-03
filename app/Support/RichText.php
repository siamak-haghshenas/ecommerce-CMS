<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * HTML from the admin's rich text editor. It is cleaned on save, so storefronts can render it
 * as-is: only safe formatting elements remain (no scripts, styles, event handlers or iframes).
 */
final class RichText
{
    /** Elements the editor produces; anything else is dropped (its text is kept). */
    private const array ALLOWED = [
        'p' => [], 'br' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
        'u' => [], 's' => [], 'a' => ['href', 'title'], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [],
        'hr' => [], 'code' => [], 'pre' => [], 'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'], 'img' => ['src', 'alt', 'width', 'height'],
    ];

    public static function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowMediaSchemes(['https', 'http'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(200_000);

        foreach (self::ALLOWED as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        $clean = trim((new HtmlSanitizer($config))->sanitize($html));

        return self::toPlainText($clean) === '' && ! str_contains($clean, '<img') ? null : $clean;
    }

    /**
     * Readable text: block ends become line breaks (so paragraphs stay paragraphs), tags are removed.
     */
    public static function toPlainText(string $html): string
    {
        $text = (string) preg_replace('#<br\s*/?>|</(p|h[1-6]|li|blockquote|tr|pre)>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
