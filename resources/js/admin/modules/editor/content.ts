/**
 * What the description editor inserts: library images and videos (with `data-media-id`, which the
 * server uses to keep them, take their addresses from the library and record that they are used)
 * and Aparat / YouTube players. No imports, so Node's test runner can load it.
 */

export interface InsertableMedia {
    id: number;
    alt: string | null;
    urls: { original: string; medium: string | null; large: string | null } | null;
}

const escapeAttribute = (value: string): string =>
    value.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

export function imageHtml(image: InsertableMedia): string {
    const src = image.urls?.large ?? image.urls?.medium ?? image.urls?.original ?? '';

    return `<img src="${escapeAttribute(src)}" alt="${escapeAttribute(image.alt ?? '')}" data-media-id="${image.id}">`;
}

export function videoHtml(video: InsertableMedia, poster: string | null): string {
    const posterAttribute = poster ? ` poster="${escapeAttribute(poster)}"` : '';

    return `<video src="${escapeAttribute(video.urls?.original ?? '')}"${posterAttribute} controls preload="metadata" data-media-id="${video.id}"></video>`;
}

/**
 * The embeddable player address of an Aparat or YouTube link (page, short or embed address), or
 * null for anything else. The server accepts exactly these addresses.
 */
export function embedUrl(link: string): string | null {
    let url: URL;

    try {
        url = new URL(link.trim());
    } catch {
        return null;
    }

    const host = url.hostname.replace(/^www\./, '').replace(/^m\./, '');
    const youtubeId = (id: string | null | undefined): string | null =>
        id && /^[A-Za-z0-9_-]{6,20}$/.test(id) ? `https://www.youtube.com/embed/${id}` : null;

    if (host === 'youtu.be') {
        return youtubeId(url.pathname.slice(1));
    }

    if (host === 'youtube.com' || host === 'youtube-nocookie.com') {
        const [, kind, id] = url.pathname.split('/');

        return kind === 'watch' || url.pathname === '/watch'
            ? youtubeId(url.searchParams.get('v'))
            : kind === 'embed' || kind === 'shorts' || kind === 'live'
              ? youtubeId(id)
              : null;
    }

    if (host === 'aparat.com') {
        const parts = url.pathname.split('/').filter(Boolean);
        const hash =
            parts[0] === 'v' ? parts[1] : parts[0] === 'video' && parts[3] === 'videohash' ? parts[4] : undefined;

        return hash && /^[A-Za-z0-9]+$/.test(hash)
            ? `https://www.aparat.com/video/video/embed/videohash/${hash}/vt/frame`
            : null;
    }

    return null;
}

export function embedHtml(src: string, title: string): string {
    return `<iframe src="${escapeAttribute(src)}" width="560" height="315" title="${escapeAttribute(title)}" allowfullscreen="allowfullscreen"></iframe>`;
}
