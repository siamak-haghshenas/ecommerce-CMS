export type MediaType = 'image' | 'document' | 'video';

export interface MediaUrls {
    original: string;
    thumb: string | null;
    medium: string | null;
    large: string | null;
}

/** Where a file is used (the details view). */
export interface MediaUsage {
    type: 'product' | 'category' | 'brand';
    id: number;
    name: string;
    role: 'gallery' | 'variant_image' | 'image' | 'logo' | 'content';
}

export interface MediaAsset {
    id: number;
    type: MediaType;
    name: string;
    mime_type: string;
    size: number;
    width: number | null;
    height: number | null;
    title: string | null;
    alt: string | null;
    urls: MediaUrls | null;
    folder: { id: number; name: string } | null;
    /** A video's cover picture. */
    cover: { id: number; thumb: string | null } | null;
    /** In the list only. */
    in_use?: boolean;
    /** In the details view only. */
    usages?: MediaUsage[];
    uploaded_by?: { id: number; name: string } | null;
    created_at: string | null;
    deleted_at: string | null;
}

export interface MediaFolder {
    id: number;
    name: string;
    files_count?: number;
}

export interface MediaFilters {
    q?: string;
    type?: MediaType;
    /** A folder id, or "none" for files in no folder. */
    folder?: number | 'none';
    used?: 0 | 1;
    trashed?: 1;
    sort?: 'newest' | 'oldest' | 'name' | 'size';
    page?: number;
    per_page?: number;
}

/** A chunked upload on the server. */
export interface MediaUploadSession {
    id: string;
    name: string;
    size: number;
    received: number;
    chunk_size: number;
    expires_at: string;
}

/** How products, categories and brands return a picture chosen from the library. */
export interface MediaPicture {
    id: number;
    original: string;
    thumb: string | null;
    medium: string | null;
    large: string | null;
    alt: string | null;
}

/** A library file as a picture (for showing a just-chosen file before saving). */
export function pictureOf(asset: MediaAsset): MediaPicture | null {
    return asset.urls ? { id: asset.id, ...asset.urls, alt: asset.alt } : null;
}
