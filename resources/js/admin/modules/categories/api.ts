import { http } from '@/shared/http/client';
import type { Resource } from '@/shared/types/api';
import type { Category, CategoryNode, SaveCategoryPayload } from './types';

export const categoriesApi = {
    /** Permission: categories.view. The whole tree, hidden categories included. */
    tree: async (): Promise<CategoryNode[]> =>
        (await http.get<Resource<CategoryNode[]>>('/admin/categories')).data.data,

    /** Permission: categories.view */
    show: async (id: number): Promise<Category> =>
        (await http.get<Resource<Category>>(`/admin/categories/${id}`)).data.data,

    /** Permission: categories.manage. Added as the last child of its parent. */
    create: async (payload: SaveCategoryPayload): Promise<Category> =>
        (await http.post<Resource<Category>>('/admin/categories', payload)).data.data,

    /** Permission: categories.manage. A new parent places the category last among its new siblings. */
    update: async (id: number, payload: SaveCategoryPayload): Promise<Category> =>
        (await http.put<Resource<Category>>(`/admin/categories/${id}`, payload)).data.data,

    /** Permission: categories.manage. Refused (409) while the category has sub-categories. */
    remove: async (id: number): Promise<void> => {
        await http.delete(`/admin/categories/${id}`);
    },

    /**
     * Permission: categories.manage. Sets the complete child order of one parent (null = top level);
     * `ids` must include every current child and may include categories moved in. Returns the new tree.
     */
    reorder: async (parentId: number | null, ids: number[]): Promise<CategoryNode[]> =>
        (await http.put<Resource<CategoryNode[]>>('/admin/categories/order', { parent_id: parentId, ids })).data.data,
};
