import type { MediaPicture } from '../media/types';

export type PageStatus = 'draft' | 'published';
/** Draft, published with a future date (scheduled) or visible now. */
export type PageState = 'draft' | 'scheduled' | 'published';

interface StaffName {
    id: number;
    name: string;
}

export interface PageListItem {
    id: number;
    title: string;
    slug: string;
    status: PageStatus;
    state: PageState;
    published_at: string | null;
    image: MediaPicture | null;
    robots_noindex: boolean;
    seo_score: number | null;
    readability_score: number | null;
    updated_by: StaffName | null;
    updated_at: string | null;
    /** Set while the page is in the trash. */
    deleted_at: string | null;
}

/** The fields a version (and an autosave) keeps besides the content. */
export interface PageSocialFields {
    robots_noindex: boolean;
    canonical_url: string | null;
    og_title: string | null;
    og_description: string | null;
    og_image: MediaPicture | null;
}

export interface Page extends PageListItem, PageSocialFields {
    excerpt: string | null;
    /** Sanitized HTML. */
    content: string | null;
    meta_title: string | null;
    meta_description: string | null;
    focus_keyphrase: string | null;
    created_by: StaffName | null;
    created_at: string | null;
    /** The viewer's unsaved form, when it is newer than the saved page. */
    autosave: PageAutosave | null;
}

/** A staff member's unsaved form (sent every 30 seconds); fields may be incomplete. */
export interface PageAutosave extends PageSocialFields {
    title: string | null;
    slug: string | null;
    excerpt: string | null;
    content: string | null;
    image: MediaPicture | null;
    meta_title: string | null;
    meta_description: string | null;
    focus_keyphrase: string | null;
    saved_at: string | null;
}

export interface PagePreview {
    token: string;
    /** The storefront's preview address with the token, or null when the store has none set. */
    url: string | null;
    expires_at: string;
}

export interface PageFilters {
    q?: string;
    status?: PageState;
    needs_seo?: 1;
    trashed?: 1;
    sort?: 'updated' | 'newest' | 'title';
    page?: number;
}

export interface SavePagePayload {
    title: string;
    slug: string;
    excerpt: string | null;
    content: string | null;
    status: PageStatus;
    /** Null: now when publishing (or the page's earlier date). */
    published_at: string | null;
    meta_title: string | null;
    meta_description: string | null;
    focus_keyphrase: string | null;
    /** The featured image: a media library image, or null. */
    image_id: number | null;
    robots_noindex: boolean;
    canonical_url: string | null;
    og_title: string | null;
    og_description: string | null;
    og_image_id: number | null;
}

/** A saved version of a page (the list has no content). */
export interface PageRevisionSummary {
    id: number;
    title: string;
    saved_by: StaffName | null;
    created_at: string | null;
}

export interface PageRevision extends PageRevisionSummary, PageSocialFields {
    slug: string;
    excerpt: string | null;
    content: string | null;
    /** Null when the file is gone or in the trash. */
    image: MediaPicture | null;
    meta_title: string | null;
    meta_description: string | null;
    focus_keyphrase: string | null;
}
