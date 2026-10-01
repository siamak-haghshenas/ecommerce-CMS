export type ActivityEvent =
    | 'customer.updated'
    | 'customer.activated'
    | 'customer.deactivated'
    | 'customer.anonymised'
    | 'staff.added'
    | 'staff.role_changed'
    | 'staff.activated'
    | 'staff.deactivated'
    | 'staff.removed';

export interface ActivityUser {
    id: number;
    name: string;
    is_anonymised: boolean;
}

/** Properties never contain personal data: field names and roles only. */
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
