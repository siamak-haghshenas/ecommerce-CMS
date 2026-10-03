import { http } from '@/shared/http/client';
import type { Resource } from '@/shared/types/api';
import type { Attribute, CategoryAttributes, Option, SaveAttributePayload, SaveOptionPayload } from './types';

export const attributesApi = {
    /** Permission: attributes.view. All attributes in order, with values (not paginated). */
    list: async (): Promise<Attribute[]> => (await http.get<Resource<Attribute[]>>('/admin/attributes')).data.data,

    /** Permission: attributes.manage. Added at the end of the list. */
    create: async (payload: SaveAttributePayload): Promise<Attribute> =>
        (await http.post<Resource<Attribute>>('/admin/attributes', payload)).data.data,

    /** Permission: attributes.manage. Values are synced: missing ones are deleted. */
    update: async (id: number, payload: SaveAttributePayload): Promise<Attribute> =>
        (await http.put<Resource<Attribute>>(`/admin/attributes/${id}`, payload)).data.data,

    /** Permission: attributes.manage */
    remove: async (id: number): Promise<void> => {
        await http.delete(`/admin/attributes/${id}`);
    },

    /** Permission: attributes.manage. `ids` must be every attribute, in the new order (409 otherwise). */
    reorder: async (ids: number[]): Promise<Attribute[]> =>
        (await http.put<Resource<Attribute[]>>('/admin/attributes/order', { ids })).data.data,

    /** Permission: categories.view */
    forCategory: async (categoryId: number): Promise<CategoryAttributes> =>
        (await http.get<Resource<CategoryAttributes>>(`/admin/categories/${categoryId}/attributes`)).data.data,

    /** Permission: categories.manage. The category's own attributes, in order. */
    setForCategory: async (categoryId: number, ids: number[]): Promise<CategoryAttributes> =>
        (await http.put<Resource<CategoryAttributes>>(`/admin/categories/${categoryId}/attributes`, { ids })).data.data,
};

export const optionsApi = {
    /** Permission: attributes.view. All options in order, with values (not paginated). */
    list: async (): Promise<Option[]> => (await http.get<Resource<Option[]>>('/admin/options')).data.data,

    /** Permission: attributes.manage */
    create: async (payload: SaveOptionPayload): Promise<Option> =>
        (await http.post<Resource<Option>>('/admin/options', payload)).data.data,

    /** Permission: attributes.manage */
    update: async (id: number, payload: SaveOptionPayload): Promise<Option> =>
        (await http.put<Resource<Option>>(`/admin/options/${id}`, payload)).data.data,

    /** Permission: attributes.manage */
    remove: async (id: number): Promise<void> => {
        await http.delete(`/admin/options/${id}`);
    },

    /** Permission: attributes.manage. `ids` must be every option, in the new order. */
    reorder: async (ids: number[]): Promise<Option[]> =>
        (await http.put<Resource<Option[]>>('/admin/options/order', { ids })).data.data,
};
