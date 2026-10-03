import { http } from '@/shared/http/client';
import type { Resource } from '@/shared/types/api';
import type { Brand, SaveBrandPayload } from './types';

export const brandsApi = {
    /** Permission: brands.view. Every brand in manual order, hidden ones included (not paginated). */
    list: async (): Promise<Brand[]> => (await http.get<Resource<Brand[]>>('/admin/brands')).data.data,

    /** Permission: brands.manage. Added at the end of the list. */
    create: async (payload: SaveBrandPayload): Promise<Brand> =>
        (await http.post<Resource<Brand>>('/admin/brands', payload)).data.data,

    /** Permission: brands.manage */
    update: async (id: number, payload: SaveBrandPayload): Promise<Brand> =>
        (await http.put<Resource<Brand>>(`/admin/brands/${id}`, payload)).data.data,

    /** Permission: brands.manage */
    remove: async (id: number): Promise<void> => {
        await http.delete(`/admin/brands/${id}`);
    },

    /** Permission: brands.manage. `ids` must be every brand, in the new order (409 otherwise). */
    reorder: async (ids: number[]): Promise<Brand[]> =>
        (await http.put<Resource<Brand[]>>('/admin/brands/order', { ids })).data.data,

    /** Permission: brands.manage. Replaces the current logo. */
    uploadLogo: async (id: number, logo: File): Promise<Brand> => {
        const body = new FormData();
        body.append('logo', logo);

        return (await http.post<Resource<Brand>>(`/admin/brands/${id}/logo`, body)).data.data;
    },

    /** Permission: brands.manage */
    removeLogo: async (id: number): Promise<Brand> =>
        (await http.delete<Resource<Brand>>(`/admin/brands/${id}/logo`)).data.data,
};
