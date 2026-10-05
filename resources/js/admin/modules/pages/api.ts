import { http } from '@/shared/http/client';
import type { Paginated, Resource } from '@/shared/types/api';
import type {
    Page,
    PageFilters,
    PageListItem,
    PagePreview,
    PageRevision,
    PageRevisionSummary,
    SavePagePayload,
} from './types';

export const pagesApi = {
    /** Permission: pages.view */
    list: async (filters: PageFilters = {}): Promise<Paginated<PageListItem>> =>
        (await http.get<Paginated<PageListItem>>('/admin/pages', { params: filters })).data,

    /** Permission: pages.view. Pages in the trash too. */
    show: async (id: number): Promise<Page> => (await http.get<Resource<Page>>(`/admin/pages/${id}`)).data.data,

    /** Permission: pages.manage */
    create: async (payload: SavePagePayload): Promise<Page> =>
        (await http.post<Resource<Page>>('/admin/pages', payload)).data.data,

    /** Permission: pages.manage */
    update: async (id: number, payload: SavePagePayload): Promise<Page> =>
        (await http.put<Resource<Page>>(`/admin/pages/${id}`, payload)).data.data,

    /** Permission: pages.manage. Moves the page to the trash (restorable for 30 days). */
    remove: async (id: number): Promise<void> => {
        await http.delete(`/admin/pages/${id}`);
    },

    /** Permission: pages.manage */
    restore: async (id: number): Promise<Page> =>
        (await http.post<Resource<Page>>(`/admin/pages/${id}/restore`)).data.data,

    /** Permission: pages.view. Newest first, without content. */
    revisions: async (id: number): Promise<PageRevisionSummary[]> =>
        (await http.get<Resource<PageRevisionSummary[]>>(`/admin/pages/${id}/revisions`)).data.data,

    /** Permission: pages.manage. Keeps the unsaved form; the page itself doesn't change. */
    autosave: async (id: number, payload: SavePagePayload): Promise<{ saved_at: string | null }> =>
        (await http.put<Resource<{ saved_at: string | null }>>(`/admin/pages/${id}/autosave`, payload)).data.data,

    /** Permission: pages.manage. "Discard" the unsaved form. */
    discardAutosave: async (id: number): Promise<void> => {
        await http.delete(`/admin/pages/${id}/autosave`);
    },

    /** Permission: pages.view. A 60-minute preview token and the storefront address showing it. */
    preview: async (id: number): Promise<PagePreview> =>
        (await http.post<Resource<PagePreview>>(`/admin/pages/${id}/preview`)).data.data,

    /** Permission: pages.view. Every saved field, to preview and restore. */
    revision: async (id: number, revisionId: number): Promise<PageRevision> =>
        (await http.get<Resource<PageRevision>>(`/admin/pages/${id}/revisions/${revisionId}`)).data.data,
};
