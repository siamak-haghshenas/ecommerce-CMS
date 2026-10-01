import { http } from '@/shared/http/client';
import type { Paginated } from '@/shared/types/api';
import type { Activity, ActivityFilters } from './types';

export const activityLogApi = {
    /** Permission: activity-log.view (super admin only) */
    list: async (filters: ActivityFilters = {}): Promise<Paginated<Activity>> =>
        (await http.get<Paginated<Activity>>('/admin/activity-log', { params: filters })).data,
};
