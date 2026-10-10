import { http } from '@/shared/http/client';
import type { Resource } from '@/shared/types/api';
import type { MediaPicture } from '../media/types';

/** The signed-in staff member's public author byline (shown on their blog posts). */
export interface Byline {
    author_name: string | null;
    slug: string | null;
    author_bio: string | null;
    avatar: MediaPicture | null;
}

export interface SaveBylinePayload {
    /** Empty removes the byline. */
    author_name: string | null;
    slug: string | null;
    author_bio: string | null;
    avatar_id: number | null;
}

export const accountApi = {
    /** Any staff member: their own byline. */
    byline: async (): Promise<Byline> => (await http.get<Resource<Byline>>('/admin/byline')).data.data,

    /** Any staff member: their own byline. */
    saveByline: async (payload: SaveBylinePayload): Promise<Byline> =>
        (await http.put<Resource<Byline>>('/admin/byline', payload)).data.data,
};
