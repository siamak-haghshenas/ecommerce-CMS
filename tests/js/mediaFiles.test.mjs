// The media library's file helpers (Node strips the TypeScript types itself).
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { byteSize, contentTypeOf, mediaTypeOf, nextChunk } from '../../resources/js/admin/modules/media/files.ts';

describe('files', () => {
    it('uses the browser type, else guesses one from the extension', () => {
        assert.equal(contentTypeOf('a.jpg', 'image/jpeg'), 'image/jpeg');
        assert.equal(contentTypeOf('clip.WEBM', ''), 'video/webm');
        assert.equal(contentTypeOf('notes.txt', ''), 'application/octet-stream');
    });

    it('knows which types the library accepts', () => {
        assert.equal(mediaTypeOf('image/avif'), 'image');
        assert.equal(mediaTypeOf('application/pdf'), 'document');
        assert.equal(mediaTypeOf('video/mp4'), 'video');
        assert.equal(mediaTypeOf('image/svg+xml'), null);
    });

    it('formats sizes in B, KB, MB and GB', () => {
        assert.deepEqual(byteSize(512), { value: 512, unit: 'B' });
        assert.deepEqual(byteSize(1536), { value: 1.5, unit: 'KB' });
        assert.deepEqual(byteSize(10 * 1024 * 1024), { value: 10, unit: 'MB' });
        assert.deepEqual(byteSize(3 * 1024 ** 3), { value: 3, unit: 'GB' });
    });

    it('plans chunks until the whole file was sent', () => {
        assert.deepEqual(nextChunk(2500, 0, 1024), [0, 1024]);
        assert.deepEqual(nextChunk(2500, 2048, 1024), [2048, 2500]);
        assert.equal(nextChunk(2500, 2500, 1024), null);
    });
});
