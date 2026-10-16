export type ActivityEvent =
    | 'customer.updated'
    | 'customer.activated'
    | 'customer.deactivated'
    | 'customer.anonymised'
    | 'staff.added'
    | 'staff.role_changed'
    | 'staff.activated'
    | 'staff.deactivated'
    | 'staff.removed'
    | 'category.created'
    | 'category.updated'
    | 'category.moved'
    | 'category.reordered'
    | 'category.deleted'
    | 'brand.created'
    | 'brand.updated'
    | 'brand.reordered'
    | 'brand.deleted'
    | 'attribute.created'
    | 'attribute.updated'
    | 'attribute.reordered'
    | 'attribute.deleted'
    | 'option.created'
    | 'option.updated'
    | 'option.reordered'
    | 'option.deleted'
    | 'category.attributes_changed'
    | 'product.created'
    | 'product.updated'
    | 'product.deleted'
    | 'product.restored'
    | 'page.created'
    | 'page.updated'
    | 'page.deleted'
    | 'page.restored'
    | 'menu.updated'
    | 'post.created'
    | 'post.updated'
    | 'post.deleted'
    | 'post.restored'
    | 'promotion.created'
    | 'promotion.updated'
    | 'promotion.deleted'
    | 'sale_event.created'
    | 'sale_event.updated'
    | 'sale_event.deleted'
    | 'sale_event.reordered'
    | 'coupon.created'
    | 'coupon.updated'
    | 'coupon.deleted'
    | 'tag.renamed'
    | 'tag.merged'
    | 'tag.deleted'
    | 'settings.updated'
    | 'media.uploaded'
    | 'media.updated'
    | 'media.moved'
    | 'media.deleted'
    | 'media.restored'
    | 'media_folder.created'
    | 'media_folder.renamed'
    | 'media_folder.deleted';

/** The user or category an entry is about (null once deleted). */
export interface ActivityUser {
    type:
        | 'user'
        | 'category'
        | 'brand'
        | 'attribute'
        | 'option'
        | 'product'
        | 'page'
        | 'post'
        | 'promotion'
        | 'sale_event'
        | 'coupon'
        | 'tag'
        | 'media'
        | 'media_folder';
    id: number;
    name: string;
    is_anonymised: boolean;
}

/** Properties never contain personal data: field names, roles, and category names (not personal). */
export interface Activity {
    id: number;
    event: ActivityEvent;
    properties: Record<string, unknown>;
    causer: ActivityUser | null;
    subject: ActivityUser | null;
    created_at: string | null;
}

export interface ActivityFilters {
    subject_id?: number;
    causer_id?: number;
    event?: ActivityEvent;
    page?: number;
    per_page?: number;
}
