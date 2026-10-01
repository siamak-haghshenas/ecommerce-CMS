import type { PermissionName, StaffRole } from '../../permissions';

export interface StaffUser {
    id: number;
    name: string;
    phone_number: string;
    email: string | null;
    locale: 'fa' | 'en';
    is_active: boolean;
    roles: StaffRole[];
    /** Effective permissions: a super admin receives the full list. */
    permissions: PermissionName[];
    phone_verified_at: string | null;
    created_at: string | null;
}
