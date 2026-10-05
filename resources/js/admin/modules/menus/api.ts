import { http } from '@/shared/http/client';
import type { Resource } from '@/shared/types/api';
import type { MenuItem, MenuLocation, MenuTargets, SaveMenuItem } from './types';

export const menusApi = {
    /** Permission: menus.view. The whole menu, hidden items included. */
    show: async (location: MenuLocation): Promise<MenuItem[]> =>
        (await http.get<Resource<MenuItem[]>>(`/admin/menus/${location}`)).data.data,

    /** Permission: menus.manage. The pages and categories items can link to. */
    targets: async (): Promise<MenuTargets> =>
        (await http.get<Resource<MenuTargets>>('/admin/menus/targets')).data.data,

    /** Permission: menus.manage. Saves the whole menu; returns it as saved. */
    save: async (location: MenuLocation, items: SaveMenuItem[]): Promise<MenuItem[]> =>
        (await http.put<Resource<MenuItem[]>>(`/admin/menus/${location}`, { items })).data.data,
};
