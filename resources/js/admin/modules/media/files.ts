import type { MediaType } from './types';

/**
 * Pure helpers of the media library (no imports besides types, so Node's test runner loads it).
 * The accepted types mirror config/media.php; the server checks the real content again.
 */

const TYPES: Record<MediaType, string[]> = {
    image: ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif'],
    document: ['application/pdf'],
    video: ['video/mp4', 'video/webm'],
};

const BY_EXTENSION: Record<string, string> = {
    jpg: 'image/jpeg',
    jpeg: 'image/jpeg',
    png: 'image/png',
    webp: 'image/webp',
    avif: 'image/avif',
    gif: 'image/gif',
    pdf: 'application/pdf',
    mp4: 'video/mp4',
    webm: 'video/webm',
};

/** The browser's content type, or one from the extension when the browser gives none. */
export function contentTypeOf(name: string, browserType: string): string {
    if (browserType !== '') {
        return browserType;
    }

    const extension = name.split('.').pop()?.toLowerCase() ?? '';

    return BY_EXTENSION[extension] ?? 'application/octet-stream';
}

/** The library type of a content type, or null when the library doesn't accept it. */
export function mediaTypeOf(contentType: string): MediaType | null {
    for (const [type, contentTypes] of Object.entries(TYPES) as [MediaType, string[]][]) {
        if (contentTypes.includes(contentType)) {
            return type;
        }
    }

    return null;
}

/** A size for people: whole bytes, else one decimal in KB / MB / GB. */
export function byteSize(bytes: number): { value: number; unit: 'B' | 'KB' | 'MB' | 'GB' } {
    const units = ['KB', 'MB', 'GB'] as const;

    if (bytes < 1024) {
        return { value: bytes, unit: 'B' };
    }

    let value = bytes / 1024;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }

    return { value: Math.round(value * 10) / 10, unit: units[unit] ?? 'GB' };
}

/** The next chunk to send: [start, end) of the file, or null when everything was sent. */
export function nextChunk(size: number, sent: number, chunkSize: number): [number, number] | null {
    return sent >= size ? null : [sent, Math.min(size, sent + chunkSize)];
}
