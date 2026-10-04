<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Short descriptions become rich text (HTML). Existing ones were plain text: each paragraph becomes
 * a <p> (line breaks become <br>), its text escaped so it stays text.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->whereNotNull('short_description')
            ->where('short_description', 'not like', '<%')
            ->orderBy('id')
            ->select(['id', 'short_description'])
            ->chunk(500, function ($products): void {
                foreach ($products as $product) {
                    $paragraphs = preg_split("/\R{2,}/", trim((string) $product->short_description)) ?: [];
                    $html = implode('', array_map(
                        fn (string $paragraph): string => '<p>'.nl2br(e(trim($paragraph)), false).'</p>',
                        array_filter($paragraphs, fn (string $paragraph): bool => trim($paragraph) !== ''),
                    ));

                    DB::table('products')->where('id', $product->id)->update(['short_description' => $html === '' ? null : $html]);
                }
            });
    }

    /**
     * Back to plain text: tags removed, paragraphs separated by a blank line.
     */
    public function down(): void
    {
        DB::table('products')->whereNotNull('short_description')->orderBy('id')->select(['id', 'short_description'])
            ->chunk(500, function ($products): void {
                foreach ($products as $product) {
                    $text = preg_replace(['#<br\s*/?>#i', '#</p>\s*#i'], ["\n", "\n\n"], (string) $product->short_description);
                    $text = trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                    DB::table('products')->where('id', $product->id)->update(['short_description' => $text === '' ? null : $text]);
                }
            });
    }
};
