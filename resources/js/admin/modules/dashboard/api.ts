import { http } from '@/shared/http/client';
import type { Resource } from '@/shared/types/api';
import type { DashboardSummary } from './types';

export const dashboardApi = {
    /** Permission: customers.view */
    summary: async (): Promise<DashboardSummary> =>
        (await http.get<Resource<DashboardSummary>>('/admin/dashboard')).data.data,
};
