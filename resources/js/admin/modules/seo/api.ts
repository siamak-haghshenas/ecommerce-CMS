import { http } from '@/shared/http/client';
import type { Resource } from '@/shared/types/api';
import type { SeoSuggestionRequest, SeoSuggestions } from './types';

export const seoApi = {
    /**
     * Needs the manage permission of the content type (categories.manage, brands.manage).
     * A paid AI call, limited per user and per day (429 when reached).
     */
    suggest: async (request: SeoSuggestionRequest): Promise<SeoSuggestions> =>
        (await http.post<Resource<SeoSuggestions>>('/admin/seo-suggestions', request)).data.data,
};
