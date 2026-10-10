import { http } from '@/shared/http/client';
import type { Paginated, Resource } from '@/shared/types/api';
import type { PagePreview, PageRevision, PageRevisionSummary } from '../pages/types';
import type { Post, PostAuthor, PostFilters, PostListItem, SavePostPayload } from './types';

export const postsApi = {
    /** Permission: posts.view */
    list: async (filters: PostFilters = {}): Promise<Paginated<PostListItem>> =>
        (await http.get<Paginated<PostListItem>>('/admin/posts', { params: filters })).data,

    /** Permission: posts.view. Staff who can be authors (for the picker and the list filter). */
    authors: async (): Promise<PostAuthor[]> =>
        (await http.get<Resource<PostAuthor[]>>('/admin/posts/authors')).data.data,

    /** Permission: posts.view. Posts in the trash too. */
    show: async (id: number): Promise<Post> => (await http.get<Resource<Post>>(`/admin/posts/${id}`)).data.data,

    /** Permission: posts.manage */
    create: async (payload: SavePostPayload): Promise<Post> =>
        (await http.post<Resource<Post>>('/admin/posts', payload)).data.data,

    /** Permission: posts.manage */
    update: async (id: number, payload: SavePostPayload): Promise<Post> =>
        (await http.put<Resource<Post>>(`/admin/posts/${id}`, payload)).data.data,

    /** Permission: posts.manage. Moves the post to the trash (restorable for 30 days). */
    remove: async (id: number): Promise<void> => {
        await http.delete(`/admin/posts/${id}`);
    },

    /** Permission: posts.manage */
    restore: async (id: number): Promise<Post> =>
        (await http.post<Resource<Post>>(`/admin/posts/${id}/restore`)).data.data,

    /** Permission: posts.manage. Keeps the unsaved form; the post itself doesn't change. */
    autosave: async (id: number, payload: SavePostPayload): Promise<{ saved_at: string | null }> =>
        (await http.put<Resource<{ saved_at: string | null }>>(`/admin/posts/${id}/autosave`, payload)).data.data,

    /** Permission: posts.manage. "Discard" the unsaved form. */
    discardAutosave: async (id: number): Promise<void> => {
        await http.delete(`/admin/posts/${id}/autosave`);
    },

    /** Permission: posts.view. A 60-minute preview token and the storefront address showing it. */
    preview: async (id: number): Promise<PagePreview> =>
        (await http.post<Resource<PagePreview>>(`/admin/posts/${id}/preview`)).data.data,

    /** Permission: posts.view. Newest first, without content. */
    revisions: async (id: number): Promise<PageRevisionSummary[]> =>
        (await http.get<Resource<PageRevisionSummary[]>>(`/admin/posts/${id}/revisions`)).data.data,

    /** Permission: posts.view. Every saved field, to preview and restore. */
    revision: async (id: number, revisionId: number): Promise<PageRevision> =>
        (await http.get<Resource<PageRevision>>(`/admin/posts/${id}/revisions/${revisionId}`)).data.data,
};
