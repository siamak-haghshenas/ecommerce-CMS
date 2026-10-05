import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { ApiError } from '@/shared/http/errors';
import { adminAuthApi } from './api';
import type { PermissionName } from '../../permissions';
import type { StaffUser } from './types';

export const useAdminAuthStore = defineStore('admin-auth', () => {
    const user = ref<StaffUser | null>(null);
    const loaded = ref(false);

    const isLoggedIn = computed(() => user.value !== null);

    /** Whether the UI should offer an action; the API enforces the same rule. */
    function can(permission: PermissionName): boolean {
        return user.value?.permissions.includes(permission) ?? false;
    }

    /** Any one of them is enough (e.g. product or blog categories). */
    function canAny(permissions: PermissionName | PermissionName[]): boolean {
        return (Array.isArray(permissions) ? permissions : [permissions]).some(can);
    }

    async function loadUser(): Promise<void> {
        if (loaded.value) {
            return;
        }

        try {
            user.value = await adminAuthApi.me();
        } catch (error) {
            // 401: not logged in. 403: logged in, but as a customer.
            if (!(error instanceof ApiError) || ![401, 403].includes(error.status)) {
                throw error;
            }
            user.value = null;
        } finally {
            loaded.value = true;
        }
    }

    function setUser(staff: StaffUser): void {
        user.value = staff;
        loaded.value = true;
    }

    async function logout(): Promise<void> {
        await adminAuthApi.logout();
        user.value = null;
    }

    return { user, loaded, isLoggedIn, can, canAny, loadUser, setUser, logout };
});
