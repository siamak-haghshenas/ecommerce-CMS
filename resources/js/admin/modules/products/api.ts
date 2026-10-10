import { http } from '@/shared/http/client';
import type { Paginated, Resource } from '@/shared/types/api';
import type { Attribute } from '../attributes/types';
import type { BulkProduct, BulkVariantPayload } from './bulkEdit';

/** A bulk edit page: products, pagination and how many products each quick filter matches. */
type BulkPage = Paginated<BulkProduct> & {
    meta: { quick_counts: Record<'all' | 'out' | 'low' | 'sale' | 'inactive', number> };
};
import type {
    Product,
    ProductFilters,
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

    /**
     * Permission: products.view. The ids of every product matching the list's filters ("select all
     * matching"); empty above `limit`, with the total.
     */
    ids: async (filters: ProductFilters = {}): Promise<{ ids: number[]; total: number; limit: number }> =>
        (
            await http.get<Resource<{ ids: number[]; total: number; limit: number }>>('/admin/products/ids', {
                params: filters,
            })
        ).data.data,

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

    /** Permission: products.manage. A page of products with every variant, plus counts per quick filter. */
    bulkList: async (filters: ProductFilters = {}): Promise<BulkPage> =>
        (await http.get<BulkPage>('/admin/products/bulk', { params: filters })).data,

    /**
     * Permission: products.manage. All or nothing: 422 with keys like `variants.3.sale_price`,
     * 409 `stock_changed` when stock moved after validation. Returns the changed variants' ids.
     */
    bulkSave: async (variants: BulkVariantPayload[]): Promise<number[]> =>
        (await http.put<Resource<{ variant_ids: number[] }>>('/admin/products/bulk', { variants })).data.data
            .variant_ids,

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
