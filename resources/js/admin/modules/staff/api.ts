import { http } from '@/shared/http/client';
import type { StaffRole } from '../../permissions';
import type { Paginated, Resource } from '@/shared/types/api';
import type { AddStaffPayload, StaffFilters, StaffMember } from './types';

export const staffApi = {
    /** Permission: staff.view */
    list: async (filters: StaffFilters = {}): Promise<Paginated<StaffMember>> =>
        (await http.get<Paginated<StaffMember>>('/admin/staff', { params: filters })).data,

    /** Permission: staff.manage (super admin only) */
    add: async (payload: AddStaffPayload): Promise<StaffMember> =>
        (await http.post<Resource<StaffMember>>('/admin/staff', payload)).data.data,

    /** Permission: staff.manage */
    changeRole: async (id: number, role: StaffRole): Promise<StaffMember> =>
        (await http.put<Resource<StaffMember>>(`/admin/staff/${id}/role`, { role })).data.data,

    /** Permission: staff.manage. Deactivating logs the member out everywhere. */
    setActive: async (id: number, isActive: boolean): Promise<StaffMember> =>
        (await http.put<Resource<StaffMember>>(`/admin/staff/${id}/status`, { is_active: isActive })).data.data,

    /** Permission: staff.manage. The account stays and becomes a customer. */
    remove: async (id: number): Promise<void> => {
        await http.delete(`/admin/staff/${id}`);
    },
};
