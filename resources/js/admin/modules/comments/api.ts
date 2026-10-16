import { http } from '@/shared/http/client';
import type { Resource } from '@/shared/types/api';
import type { Comment, CommentableType, CommentFilters, CommentList, ModeratedStatus } from './types';

/** Every call needs comments.moderate. */
export const commentsApi = {
    /** Newest first; pending unless `status` says otherwise; `meta.status_counts` for the tabs. */
    list: async (filters: CommentFilters = {}): Promise<CommentList> =>
        (await http.get<CommentList>('/admin/comments', { params: filters })).data,

    /** Per kind, for the menu badges. */
    pendingCount: async (): Promise<Record<CommentableType, number>> =>
        (await http.get<Resource<{ pending: Record<CommentableType, number> }>>('/admin/comments/pending-count')).data
            .data.pending,

    /** 409 `comment_status_unchanged` when it already has the status; `reason` only with rejected. */
    moderate: async (id: number, status: ModeratedStatus, reason: string | null = null): Promise<Comment> =>
        (await http.put<Resource<Comment>>(`/admin/comments/${id}/status`, { status, reason })).data.data,

    /** All or nothing; comments that already have the status are left as they are. */
    moderateMany: async (ids: number[], status: ModeratedStatus, reason: string | null = null): Promise<number> =>
        (await http.post<Resource<{ changed: number }>>('/admin/comments/status', { ids, status, reason })).data.data
            .changed,

    reply: async (id: number, reply: string): Promise<Comment> =>
        (await http.put<Resource<Comment>>(`/admin/comments/${id}/reply`, { reply })).data.data,

    removeReply: async (id: number): Promise<Comment> =>
        (await http.delete<Resource<Comment>>(`/admin/comments/${id}/reply`)).data.data,

    /** For good. */
    remove: async (id: number): Promise<void> => {
        await http.delete(`/admin/comments/${id}`);
    },

    /** For good. */
    removeMany: async (ids: number[]): Promise<number> =>
        (await http.post<Resource<{ deleted: number }>>('/admin/comments/delete', { ids })).data.data.deleted,
};
