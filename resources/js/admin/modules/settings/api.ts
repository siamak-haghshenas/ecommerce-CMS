import { http } from '@/shared/http/client';
import type { Resource } from '@/shared/types/api';
import type { StoreSettings } from './types';

export const settingsApi = {
    /** Every staff member can read the settings. */
    get: async (): Promise<StoreSettings> => (await http.get<Resource<StoreSettings>>('/admin/settings')).data.data,

    /** Permission: settings.manage (super admin). Any subset of the settings. */
    update: async (values: Partial<StoreSettings>): Promise<StoreSettings> =>
        (await http.put<Resource<StoreSettings>>('/admin/settings', values)).data.data,
};
