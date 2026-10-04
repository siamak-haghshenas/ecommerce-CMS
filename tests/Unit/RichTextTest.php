<?php

declare(strict_types=1);

use App\Support\RichText;

it('keeps formatting and removes anything that could run code', function (string $html, ?string $expected) {
    expect(RichText::sanitize($html))->toBe($expected);
})->with([
    'formatting' => ['<h2>Size</h2><p>Soft <strong>cotton</strong>, <em>not</em> wool.</p><ul><li>S</li></ul>', '<h2>Size</h2><p>Soft <strong>cotton</strong>, <em>not</em> wool.</p><ul><li>S</li></ul>'],
    'script' => ['<p>Hi</p><script>alert(1)</script>', '<p>Hi</p>'],
    'event handler' => ['<p onclick="steal()">Hi</p>', '<p>Hi</p>'],
    'javascript link' => ['<a href="javascript:alert(1)">x</a>', '<a rel="noopener noreferrer">x</a>'],
    'safe link' => ['<a href="https://shop.test/a">x</a>', '<a href="https://shop.test/a" rel="noopener noreferrer">x</a>'],
    'unknown iframe' => ['<iframe src="https://evil.test"></iframe><p>x</p>', '<p>x</p>'],
    'only empty markup' => ['<p> </p><p><br></p>', null],
]);

it('keeps only safe style properties and values', function (string $style, ?string $kept) {
    $html = RichText::sanitize("<p style=\"{$style}\">x</p>");

    expect($html)->toBe($kept === null ? '<p>x</p>' : "<p style=\"{$kept}\">x</p>");
})->with([
    'colour and alignment' => ['color: #c00; text-align: center', 'color: #c00; text-align: center'],
    'background' => ['background-color: rgb(255, 230, 0)', 'background-color: rgb(255, 230, 0)'],
    'positioning and urls dropped' => ['position: fixed; background-image: url(https://evil.test/x.png); color: red', 'color: red'],
    'expressions dropped' => ['color: expression(alert(1))', null],
]);

it('embeds only Aparat and YouTube players', function () {
    $youtube = '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="560" height="315"></iframe>';
    $aparat = '<iframe src="https://www.aparat.com/video/video/embed/videohash/abc123/vt/frame"></iframe>';
    $lookalike = '<iframe src="https://www.youtube.com.evil.test/embed/dQw4w9WgXcQ"></iframe>';

    expect(RichText::sanitize($youtube))->toContain('src="https://www.youtube.com/embed/dQw4w9WgXcQ"')->toContain('allowfullscreen')
        ->and(RichText::sanitize($aparat))->toContain('aparat.com/video/video/embed/videohash/abc123/vt/frame')
        ->and(RichText::sanitize($lookalike))->toBeNull();
});

it('keeps only inline formatting, links and lists in the short description', function () {
    expect(RichText::sanitize('<h2>Big</h2><p style="color:red">Soft <strong>cotton</strong></p><table><tr><td>x</td></tr></table>', full: false))
        ->toBe('<p>Big</p><p>Soft <strong>cotton</strong></p>x');
});

it('lists the library files the HTML uses', function () {
    expect(RichText::mediaIds('<img data-media-id="7" src="a"><video data-media-id="9"></video><img data-media-id="7">'))->toBe([7, 9]);
});

it('turns HTML into readable plain text for the SEO analysis', function () {
    expect(RichText::toPlainText('<h2>Fabric</h2><p>Soft&nbsp;cotton &amp; linen.</p><ul><li>S</li><li>M</li></ul>'))
        ->toBe("Fabric\nSoft\u{00A0}cotton & linen.\nS\nM");
});
