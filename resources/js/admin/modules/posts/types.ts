import type { MediaPicture } from '../media/types';
import type { PageAutosave, PageSocialFields, PageState } from '../pages/types';

export type PostStatus = 'draft' | 'published';
/** Draft, published with a future date (scheduled) or visible now. */
export type PostState = PageState;

interface StaffName {
    id: number;
    name: string;
}

/** A staff member as an author: the account name for staff; storefronts only show the byline. */
export interface PostAuthor extends StaffName {
    author_name: string | null;
    has_byline?: boolean;
}

export interface PostListItem {
    id: number;
    title: string;
    slug: string;
    status: PostStatus;
    state: PostState;
    published_at: string | null;
    image: MediaPicture | null;
    robots_noindex: boolean;
    author: PostAuthor | null;
    primary_category: { id: number; name: string } | null;
    seo_score: number | null;
    readability_score: number | null;
    updated_by: StaffName | null;
    updated_at: string | null;
    /** Set while the post is in the trash. */
    deleted_at: string | null;
}

export interface Post extends PostListItem, PageSocialFields {
    excerpt: string | null;
    /** Sanitized HTML. */
    content: string | null;
    comments_enabled: boolean;
    categories: { id: number; name: string; is_primary: boolean }[];
    tags: string[];
    meta_title: string | null;
    meta_description: string | null;
    focus_keyphrase: string | null;
    created_by: StaffName | null;
    created_at: string | null;
    /** The viewer's unsaved form, when it is newer than the saved post. */
    autosave: PostAutosave | null;
}

/** The same fields as a page's autosave (categories and tags are not autosaved). */
export type PostAutosave = PageAutosave;

export interface PostFilters {
    q?: string;
    status?: PostState;
    category_id?: number;
    tag_id?: number;
    author_id?: number;
    needs_seo?: 1;
    trashed?: 1;
    sort?: 'updated' | 'newest' | 'title';
    page?: number;
}

export interface SavePostPayload {
    title: string;
    slug: string;
    excerpt: string | null;
    content: string | null;
    status: PostStatus;
    /** Null: now when publishing (or the post's earlier date). */
    published_at: string | null;
    /** A staff member; null keeps the current one (new posts: the writer). */
    author_id: number | null;
    comments_enabled: boolean;
    category_ids: number[];
    primary_category_id: number | null;
    tags: string[];
    meta_title: string | null;
    meta_description: string | null;
    focus_keyphrase: string | null;
    image_id: number | null;
    robots_noindex: boolean;
    canonical_url: string | null;
    og_title: string | null;
    og_description: string | null;
    og_image_id: number | null;
}
