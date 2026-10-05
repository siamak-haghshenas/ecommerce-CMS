import { http } from '@/shared/http/client';
import type { Resource } from '@/shared/types/api';
import type { Category, CategoryNode, CategoryType, SaveCategoryPayload } from './types';

export const categoriesApi = {
    /** Permission: categories.view (product) or blog_categories.manage (blog). One type's tree, hidden categories included. */
    tree: async (type: CategoryType = 'product'): Promise<CategoryNode[]> =>
        (await http.get<Resource<CategoryNode[]>>('/admin/categories', { params: { type } })).data.data,

    /** Permission: the category type's view permission. */
    show: async (id: number): Promise<Category> =>
        (await http.get<Resource<Category>>(`/admin/categories/${id}`)).data.data,

    /** Permission: the type's manage permission. Added as the last child of its parent. */
    create: async (payload: SaveCategoryPayload): Promise<Category> =>
        (await http.post<Resource<Category>>('/admin/categories', payload)).data.data,

    /** Permission: the type's manage permission. A new parent places the category last among its new siblings. */
    update: async (id: number, payload: SaveCategoryPayload): Promise<Category> =>
        (await http.put<Resource<Category>>(`/admin/categories/${id}`, payload)).data.data,

    /** Permission: the type's manage permission. Refused (409) while the category has sub-categories. */
    remove: async (id: number): Promise<void> => {
        await http.delete(`/admin/categories/${id}`);
    },

    /**
     * Permission: the type's manage permission. Sets the complete child order of one parent (null =
     * the type's top level); `ids` must include every current child and may include categories moved
     * in. Returns the type's new tree.
     */
    reorder: async (parentId: number | null, ids: number[], type: CategoryType = 'product'): Promise<CategoryNode[]> =>
        (await http.put<Resource<CategoryNode[]>>('/admin/categories/order', { type, parent_id: parentId, ids })).data
            .data,
};
