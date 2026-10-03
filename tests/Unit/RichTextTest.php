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
    'iframe and style' => ['<iframe src="https://evil.test"></iframe><p style="color:red">x</p>', '<p>x</p>'],
    'only empty markup' => ['<p> </p><p><br></p>', null],
]);

it('turns HTML into readable plain text for the SEO analysis', function () {
    expect(RichText::toPlainText('<h2>Fabric</h2><p>Soft&nbsp;cotton &amp; linen.</p><ul><li>S</li><li>M</li></ul>'))
        ->toBe("Fabric\nSoft\u{00A0}cotton & linen.\nS\nM");
});
