import { http } from '@/shared/http/client';
import type { Paginated, Resource } from '@/shared/types/api';
import type { MediaAsset, MediaFilters, MediaFolder, MediaUploadSession } from './types';

export const mediaApi = {
    /** Permission: media.view */
    list: async (filters: MediaFilters = {}): Promise<Paginated<MediaAsset>> =>
        (await http.get<Paginated<MediaAsset>>('/admin/media', { params: filters })).data,

    /** Permission: media.view. With "where it's used"; files in the trash too. */
    show: async (id: number): Promise<MediaAsset> =>
        (await http.get<Resource<MediaAsset>>(`/admin/media/${id}`)).data.data,

    /** Permission: media.upload */
    update: async (
        id: number,
        payload: { title: string | null; alt: string | null; cover_id: number | null },
    ): Promise<MediaAsset> => (await http.put<Resource<MediaAsset>>(`/admin/media/${id}`, payload)).data.data,

    /** Permission: media.manage. `folderId` null: no folder. */
    move: async (ids: number[], folderId: number | null): Promise<number> =>
        (await http.post<Resource<{ moved: number }>>('/admin/media/move', { ids, folder_id: folderId })).data.data
            .moved,

    /** Permission: media.manage. To the trash; 409 `media_in_use` when something uses it. */
    remove: async (id: number): Promise<void> => {
        await http.delete(`/admin/media/${id}`);
    },

    /** Permission: media.manage */
    restore: async (id: number): Promise<MediaAsset> =>
        (await http.post<Resource<MediaAsset>>(`/admin/media/${id}/restore`)).data.data,

    // ---- chunked uploads (permission: media.upload) ----

    /** Announces a file; its type and size are checked before anything is sent. */
    startUpload: async (file: {
        name: string;
        mime_type: string;
        size: number;
        folder_id: number | null;
    }): Promise<MediaUploadSession> =>
        (await http.post<Resource<MediaUploadSession>>('/admin/media/uploads', file)).data.data,

    /** 409 `media_chunk_out_of_order` (with `received`) when the chunk doesn't continue the file. */
    sendChunk: async (id: string, chunk: Blob, offset: number, signal?: AbortSignal): Promise<MediaUploadSession> => {
        const body = new FormData();
        body.append('chunk', chunk, 'chunk');
        body.append('offset', String(offset));

        return (await http.post<Resource<MediaUploadSession>>(`/admin/media/uploads/${id}/chunks`, body, { signal }))
            .data.data;
    },

    completeUpload: async (id: string): Promise<MediaAsset> =>
        (await http.post<Resource<MediaAsset>>(`/admin/media/uploads/${id}/complete`)).data.data,

    cancelUpload: async (id: string): Promise<void> => {
        await http.delete(`/admin/media/uploads/${id}`);
    },

    // ---- folders ----

    /** Permission: media.view. By name, with file counts. */
    folders: async (): Promise<MediaFolder[]> =>
        (await http.get<Resource<MediaFolder[]>>('/admin/media/folders')).data.data,

    /** Permission: media.manage */
    createFolder: async (name: string): Promise<MediaFolder> =>
        (await http.post<Resource<MediaFolder>>('/admin/media/folders', { name })).data.data,

    /** Permission: media.manage */
    renameFolder: async (id: number, name: string): Promise<MediaFolder> =>
        (await http.put<Resource<MediaFolder>>(`/admin/media/folders/${id}`, { name })).data.data,

    /** Permission: media.manage. Its files move to "no folder". */
    deleteFolder: async (id: number): Promise<void> => {
        await http.delete(`/admin/media/folders/${id}`);
    },
};
