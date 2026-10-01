import type { StaffRole } from '../../permissions';

export interface StaffMember {
    id: number;
    name: string;
    phone_number: string;
    email: string | null;
    role: StaffRole;
    is_active: boolean;
    last_login_at: string | null;
    created_at: string | null;
}

export interface StaffFilters {
    q?: string;
    role?: StaffRole;
    status?: 'active' | 'inactive';
    page?: number;
    per_page?: number;
}

/**
 * An existing customer with this number is promoted; otherwise a new account is created
 * (then `name` is required). Staff log in with an SMS code; no password is set.
 */
export interface AddStaffPayload {
    phone_number: string;
    name?: string;
    role: StaffRole;
}
