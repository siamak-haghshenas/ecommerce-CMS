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
    | 'category.attributes_changed';

/** The user or category an entry is about (null once deleted). */
export interface ActivityUser {
    type: 'user' | 'category' | 'brand' | 'attribute' | 'option';
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
