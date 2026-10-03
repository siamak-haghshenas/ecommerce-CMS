/** Content types using the shared SEO analysis (mirrors App\Enums\SeoContentType). */
export type SeoContentType = 'category' | 'brand' | 'product';

/** The form as currently typed; the item may not exist yet. */
export interface SeoSuggestionRequest {
    type: SeoContentType;
    name: string;
    /** Another way the name is written, e.g. a brand's Persian name. */
    alternate_name?: string | null;
    description: string | null;
    focus_keyphrase: string | null;
    /** Categories only: the parent, for context. */
    parent_id?: number | null;
    /** The saved item, when it exists. */
    subject_id: number | null;
}

/** Three options per field; nothing is saved until the admin saves the form. */
export interface SeoSuggestions {
    keyphrases: string[];
    titles: string[];
    meta_descriptions: string[];
}
