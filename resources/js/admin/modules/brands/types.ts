import type { MediaPicture } from '../media/types';

/** A brand's logo: a file of the media library. */
export type BrandLogo = MediaPicture;

export interface Brand {
    id: number;
    /** As the brand writes it, e.g. Adidas. */
    name: string;
    /** How Persian-speaking shoppers write it, e.g. آدیداس. */
    persian_name: string | null;
    slug: string;
    description: string | null;
    website: string | null;
    position: number;
    is_active: boolean;
    logo: BrandLogo | null;
    meta_title: string | null;
    meta_description: string | null;
    focus_keyphrase: string | null;
    /** Stored on save; null until analysed. */
    seo_score: number | null;
    readability_score: number | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface SaveBrandPayload {
    name: string;
    persian_name: string | null;
    slug: string;
    description: string | null;
    website: string | null;
    meta_title: string | null;
    meta_description: string | null;
    focus_keyphrase: string | null;
    is_active: boolean;
    /** A media library image, or null. */
    logo_id: number | null;
}
