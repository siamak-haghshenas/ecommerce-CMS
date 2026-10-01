import { http } from '@/shared/http/client';
import type { Paginated, Resource } from '@/shared/types/api';
import type { Customer, CustomerFilters, UpdateCustomerPayload } from './types';

export const customersApi = {
    /** Permission: customers.view */
    list: async (filters: CustomerFilters = {}): Promise<Paginated<Customer>> =>
        (await http.get<Paginated<Customer>>('/admin/customers', { params: filters })).data,

    /** Permission: customers.view */
    show: async (id: number): Promise<Customer> =>
        (await http.get<Resource<Customer>>(`/admin/customers/${id}`)).data.data,

    /** Permission: customers.update */
    update: async (id: number, payload: UpdateCustomerPayload): Promise<Customer> =>
        (await http.put<Resource<Customer>>(`/admin/customers/${id}`, payload)).data.data,

    /** Permission: customers.deactivate. Deactivating logs the customer out everywhere. */
    setActive: async (id: number, isActive: boolean): Promise<Customer> =>
        (await http.put<Resource<Customer>>(`/admin/customers/${id}/status`, { is_active: isActive })).data.data,

    /** Permission: customers.anonymise (super admin only). Irreversible. */
    anonymise: async (id: number): Promise<Customer> =>
        (await http.post<Resource<Customer>>(`/admin/customers/${id}/anonymise`)).data.data,
};
