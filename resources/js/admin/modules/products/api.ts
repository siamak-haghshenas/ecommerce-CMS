import { http } from '@/shared/http/client';
import type { Paginated, Resource } from '@/shared/types/api';
import type { Attribute } from '../attributes/types';
import type {
    Product,
    ProductFilters,
    ProductImage,
    ProductListItem,
    ProductStatus,
    SaveProductPayload,
    StockMovement,
    Tag,
} from './types';

export const productsApi = {
    /** Permission: products.view */
    list: async (filters: ProductFilters = {}): Promise<Paginated<ProductListItem>> =>
        (await http.get<Paginated<ProductListItem>>('/admin/products', { params: filters })).data,

    /** Permission: products.view. Deleted products too. */
    show: async (id: number): Promise<Product> =>
        (await http.get<Resource<Product>>(`/admin/products/${id}`)).data.data,

    /** Permission: products.manage */
    create: async (payload: SaveProductPayload): Promise<Product> =>
        (await http.post<Resource<Product>>('/admin/products', payload)).data.data,

    /** Permission: products.manage. 409 `stock_changed` when stock moved since the product was loaded. */
    update: async (id: number, payload: SaveProductPayload): Promise<Product> =>
        (await http.put<Resource<Product>>(`/admin/products/${id}`, payload)).data.data,

    /** Permission: products.manage. A soft delete: it can be restored. */
    remove: async (id: number): Promise<void> => {
        await http.delete(`/admin/products/${id}`);
    },

    /** Permission: products.manage */
    restore: async (id: number): Promise<Product> =>
        (await http.post<Resource<Product>>(`/admin/products/${id}/restore`)).data.data,

    /** Permission: products.manage. Products that can't be published are skipped. */
    changeStatus: async (ids: number[], status: ProductStatus): Promise<{ updated: number[]; skipped: number[] }> =>
        (await http.post<Resource<{ updated: number[]; skipped: number[] }>>('/admin/products/status', { ids, status }))
            .data.data,

    /** Permission: products.manage. Returns the gallery in order. */
    uploadImage: async (id: number, image: File): Promise<ProductImage[]> => {
        const body = new FormData();
        body.append('image', image);

        return (await http.post<Resource<ProductImage[]>>(`/admin/products/${id}/images`, body)).data.data;
    },

    /** Permission: products.manage. `ids`: every image, in the new order (the first is the main image). */
    reorderImages: async (id: number, ids: number[]): Promise<ProductImage[]> =>
        (await http.put<Resource<ProductImage[]>>(`/admin/products/${id}/images/order`, { ids })).data.data,

    /** Permission: products.manage */
    removeImage: async (id: number, mediaId: number): Promise<ProductImage[]> =>
        (await http.delete<Resource<ProductImage[]>>(`/admin/products/${id}/images/${mediaId}`)).data.data,

    /** Permission: products.view */
    stockMovements: async (id: number, page = 1): Promise<Paginated<StockMovement>> =>
        (await http.get<Paginated<StockMovement>>(`/admin/products/${id}/stock-movements`, { params: { page } })).data,

    /** Permission: products.view. The specifications products in these categories can have. */
    applicableAttributes: async (categoryIds: number[]): Promise<Attribute[]> =>
        (
            await http.get<Resource<Attribute[]>>('/admin/attributes/applicable', {
                params: { category_ids: categoryIds },
            })
        ).data.data,
};

export const tagsApi = {
    /** Permission: products.view. `q` filters while typing. */
    list: async (q?: string): Promise<Tag[]> =>
        (await http.get<Resource<Tag[]>>('/admin/tags', { params: q ? { q } : {} })).data.data,

    /** Permission: products.manage */
    rename: async (id: number, name: string): Promise<Tag> =>
        (await http.put<Resource<Tag>>(`/admin/tags/${id}`, { name })).data.data,

    /** Permission: products.manage. Moves the products of `id` to `intoId`, then deletes `id`. */
    merge: async (id: number, intoId: number): Promise<Tag> =>
        (await http.post<Resource<Tag>>(`/admin/tags/${id}/merge`, { into_tag_id: intoId })).data.data,

    /** Permission: products.manage */
    remove: async (id: number): Promise<void> => {
        await http.delete(`/admin/tags/${id}`);
    },
};
