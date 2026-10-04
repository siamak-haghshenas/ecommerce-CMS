import type { MediaPicture } from '../media/types';

/** A category's image: a file of the media library. */
export type CategoryImage = MediaPicture;

/** One node of the admin tree (GET /admin/categories). */
export interface CategoryNode {
    id: number;
    parent_id: number | null;
    name: string;
    slug: string;
    position: number;
    is_active: boolean;
    /** False when the category or one of its ancestors is hidden ("hidden by parent"). */
    is_visible: boolean;
    image: CategoryImage | null;
    focus_keyphrase: string | null;
    /** Stored on save; null until analysed. */
    seo_score: number | null;
    readability_score: number | null;
    children: CategoryNode[];
}

/** A single category with all its fields (GET /admin/categories/{id}). */
export interface Category {
    id: number;
    parent_id: number | null;
    name: string;
    slug: string;
    description: string | null;
    meta_title: string | null;
    meta_description: string | null;
    focus_keyphrase: string | null;
    seo_score: number | null;
    readability_score: number | null;
    position: number;
    is_active: boolean;
    is_visible: boolean;
    image: CategoryImage | null;
    ancestors: { id: number; name: string; slug: string }[];
    children_count: number;
    created_at: string | null;
    updated_at: string | null;
}

export interface SaveCategoryPayload {
    name: string;
    slug: string;
    parent_id: number | null;
    description: string | null;
    meta_title: string | null;
    meta_description: string | null;
    focus_keyphrase: string | null;
    is_active: boolean;
    /** A media library image, or null. */
    image_id: number | null;
}
