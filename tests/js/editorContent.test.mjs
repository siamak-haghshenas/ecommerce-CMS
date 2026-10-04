// What the description editor inserts (Node strips the TypeScript types itself).
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { embedHtml, embedUrl, imageHtml, videoHtml } from '../../resources/js/admin/modules/editor/content.ts';

describe('embedUrl', () => {
    it('turns YouTube links into the embed address', () => {
        for (const link of [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ',
            'https://m.youtube.com/watch?v=dQw4w9WgXcQ&t=10',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
        ]) {
            assert.equal(embedUrl(link), 'https://www.youtube.com/embed/dQw4w9WgXcQ', link);
        }
    });

    it('turns Aparat links into the embed address', () => {
        const embed = 'https://www.aparat.com/video/video/embed/videohash/abc123/vt/frame';

        assert.equal(embedUrl('https://www.aparat.com/v/abc123'), embed);
        assert.equal(embedUrl(embed), embed);
    });

    it('refuses anything else', () => {
        for (const link of ['https://vimeo.com/1', 'https://www.youtube.com.evil.test/watch?v=dQw4w9WgXcQ', 'not a link', 'javascript:alert(1)']) {
            assert.equal(embedUrl(link), null, link);
        }
    });
});

describe('inserted media', () => {
    const image = { id: 7, alt: 'A "red" shirt', urls: { original: '/o.jpg', medium: '/m.webp', large: '/l.webp' } };

    it('marks library files and escapes attributes', () => {
        assert.equal(imageHtml(image), '<img src="/l.webp" alt="A &quot;red&quot; shirt" data-media-id="7">');
        assert.equal(
            videoHtml({ id: 9, alt: null, urls: { original: '/v.mp4', medium: null, large: null } }, '/cover.webp'),
            '<video src="/v.mp4" poster="/cover.webp" controls preload="metadata" data-media-id="9"></video>',
        );
        assert.match(embedHtml('https://www.youtube.com/embed/x1234567', 'Video'), /^<iframe src="https:\/\/www\.youtube\.com\/embed\/x1234567"/);
    });
});
