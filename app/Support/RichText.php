<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\MediaType;
use App\Models\MediaAsset;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * HTML from the admin's rich text editor (TinyMCE). It is cleaned on save, so storefronts can
 * render it as-is: no scripts, event handlers or unknown embeds; styles keep only a few safe
 * properties; images and videos must be files of the media library (`data-media-id`), whose
 * addresses are taken from the library; iframes only for Aparat and YouTube players.
 */
final class RichText
{
    /** The short description: inline formatting, links and lists. */
    private const array BASIC = [
        'p' => ['dir'], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'a' => ['href', 'title'], 'ul' => [], 'ol' => [], 'li' => [],
    ];

    /** The long description. */
    private const array FULL = [
        'p' => ['style', 'dir'], 'div' => ['style', 'dir'], 'br' => [], 'h2' => ['style', 'dir'], 'h3' => ['style', 'dir'],
        'h4' => ['style', 'dir'], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'sub' => [], 'sup' => [],
        'span' => ['style', 'dir'], 'a' => ['href', 'title'], 'ul' => ['style', 'dir'], 'ol' => ['style', 'dir'], 'li' => ['style', 'dir'],
        'blockquote' => ['style', 'dir'], 'hr' => [], 'code' => [], 'pre' => [], 'table' => ['style', 'dir'], 'caption' => [],
        'colgroup' => [], 'col' => ['style'], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => ['style'],
        'th' => ['colspan', 'rowspan', 'style', 'dir'], 'td' => ['colspan', 'rowspan', 'style', 'dir'],
        'figure' => [], 'figcaption' => [], 'img' => ['src', 'alt', 'width', 'height', 'data-media-id'],
        'video' => ['src', 'poster', 'controls', 'preload', 'width', 'height', 'data-media-id'],
        'iframe' => ['src', 'width', 'height', 'title', 'allowfullscreen'],
    ];

    /** Elements whose markup is removed but whose text is kept when a profile doesn't allow them. */
    private const array KEEP_TEXT = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'font', 'center', 'section', 'article', 'blockquote', 'pre', 'code',
        's', 'sub', 'sup', 'small', 'mark', 'table', 'caption', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'figure', 'figcaption',
    ];

    /** CSS properties kept in `style`, each with the values it may have. */
    private const array STYLES = [
        'color' => '/^(#[0-9a-f]{3,8}|rgba?\([\d\s.,%]+\)|[a-z]{3,20})$/i',
        'background-color' => '/^(#[0-9a-f]{3,8}|rgba?\([\d\s.,%]+\)|[a-z]{3,20})$/i',
        'text-align' => '/^(left|right|center|justify|start|end)$/',
        'width' => '/^\d{1,4}(\.\d+)?(px|%)$/',
        'border-collapse' => '/^(collapse|separate)$/',
    ];

    /** Players that may be embedded: Aparat and YouTube (privacy-enhanced too). */
    private const array EMBEDS = [
        '#^https://www\.aparat\.com/video/video/embed/videohash/[A-Za-z0-9]+/vt/frame$#',
        '#^https://www\.youtube(-nocookie)?\.com/embed/[A-Za-z0-9_-]{6,20}$#',
    ];

    /**
     * @param  bool  $full  the long description's editor (media, tables, colours) or the short one's
     */
    public static function sanitize(?string $html, bool $full = true): ?string
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
            ->withMaxInputLength(500_000);

        $allowed = $full ? self::FULL : self::BASIC;

        foreach ($allowed as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        foreach (array_diff(self::KEEP_TEXT, array_keys($allowed)) as $element) {
            $config = $config->blockElement($element);
        }

        if (! $full) {
            // Pasted headings and blocks become paragraphs, so their text stays separate.
            $html = (string) preg_replace(['#<(h[1-6]|div|blockquote|pre)(\s[^>]*)?>#i', '#</(h[1-6]|div|blockquote|pre)>#i'], ['<p>', '</p>'], $html);
        }

        $clean = trim((new HtmlSanitizer($config))->sanitize($html));

        if ($full) {
            $clean = self::refine($clean);
        }

        return self::toPlainText($clean) === '' && ! preg_match('/<(img|video|iframe)\b/i', $clean) ? null : $clean;
    }

    /**
     * Readable text: block ends become line breaks (so paragraphs stay paragraphs), tags are removed.
     */
    public static function toPlainText(string $html): string
    {
        $text = (string) preg_replace('#<br\s*/?>|</(p|div|h[1-6]|li|blockquote|tr|pre|figcaption)>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /**
     * The media library files the HTML uses (images and videos), without duplicates.
     *
     * @return list<int>
     */
    public static function mediaIds(?string $html): array
    {
        preg_match_all('/data-media-id="(\d+)"/', (string) $html, $matches);

        return array_values(array_unique(array_map(intval(...), $matches[1])));
    }

    /**
     * After the sanitizer: filter styles, resolve library media and keep only known players.
     */
    private static function refine(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div id="rich-text-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);

        foreach (self::elements($xpath, '//*[@style]') as $element) {
            $style = self::filterStyle($element->getAttribute('style'));
            $style === '' ? $element->removeAttribute('style') : $element->setAttribute('style', $style);
        }

        self::resolveMedia($xpath);

        foreach (self::elements($xpath, '//iframe') as $frame) {
            $source = $frame->getAttribute('src');

            if (! array_filter(self::EMBEDS, fn (string $pattern): bool => preg_match($pattern, $source) === 1)) {
                $frame->parentNode?->removeChild($frame);

                continue;
            }

            $frame->setAttribute('allowfullscreen', 'allowfullscreen');
        }

        $root = $document->getElementById('rich-text-root');
        $result = '';

        foreach ($root === null ? [] : $root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result);
    }

    /**
     * Images and videos must be files of the library: their addresses (and a video's poster) come
     * from the file, so a pasted or forged address can't be kept.
     */
    private static function resolveMedia(DOMXPath $xpath): void
    {
        $nodes = self::elements($xpath, '//img | //video');

        if ($nodes === []) {
            return;
        }

        $ids = array_map(fn (DOMElement $node): int => (int) $node->getAttribute('data-media-id'), $nodes);
        $assets = MediaAsset::query()->whereKey(array_filter($ids))->with(['media', 'cover.media'])->get()->keyBy('id');

        foreach ($nodes as $node) {
            $asset = $assets->get((int) $node->getAttribute('data-media-id'));
            $expected = $node->tagName === 'img' ? MediaType::Image : MediaType::Video;
            $urls = $asset?->type === $expected ? $asset->urls() : null;

            if ($asset === null || $urls === null) {
                $node->parentNode?->removeChild($node);

                continue;
            }

            if ($node->tagName === 'img') {
                $node->setAttribute('src', $urls['large'] ?? $urls['original']);

                if (! $node->hasAttribute('alt')) {
                    $node->setAttribute('alt', (string) $asset->alt);
                }

                continue;
            }

            $node->setAttribute('src', $urls['original']);
            $node->setAttribute('controls', 'controls');
            $node->setAttribute('preload', 'metadata');
            $poster = $asset->cover?->urls();
            $poster === null ? $node->removeAttribute('poster') : $node->setAttribute('poster', $poster['large'] ?? $poster['original']);
        }
    }

    private static function filterStyle(string $style): string
    {
        $kept = [];

        foreach (explode(';', $style) as $declaration) {
            [$property, $value] = array_map('trim', explode(':', $declaration, 2) + [1 => '']);
            $property = strtolower($property);

            if (isset(self::STYLES[$property]) && preg_match(self::STYLES[$property], $value) === 1) {
                $kept[] = "{$property}: {$value}";
            }
        }

        return implode('; ', $kept);
    }

    /**
     * @return list<DOMElement>
     */
    private static function elements(DOMXPath $xpath, string $query): array
    {
        $found = [];

        foreach ($xpath->query($query) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $found[] = $node;
            }
        }

        return $found;
    }
}
