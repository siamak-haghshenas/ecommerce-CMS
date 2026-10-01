import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toApiError } from '@/shared/http/errors';
import type { ActionMenuItem } from '@/shared/ui/ActionMenu.vue';
import { useToast } from '@/shared/composables/useToast';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { customersApi } from '../api';
import type { Customer } from '../types';

export type CustomerDialog = 'edit' | 'deactivate' | 'anonymise';

/**
 * The actions a staff member may take on a customer, and the dialog state that runs them.
 * Shared by the list and detail pages; `onUpdated` receives the customer as the API returned it.
 */
export function useCustomerActions(onUpdated: (customer: Customer) => void) {
    const { t } = useI18n();
    const toast = useToast();
    const auth = useAdminAuthStore();

    const dialog = ref<CustomerDialog | null>(null);
    const target = ref<Customer | null>(null);
    const activating = ref<number | null>(null);

    const canUpdate = computed(() => auth.can(Permission.UpdateCustomers));
    const canDeactivate = computed(() => auth.can(Permission.DeactivateCustomers));
    const canAnonymise = computed(() => auth.can(Permission.AnonymiseCustomers));

    /** Menu entries for one customer; anonymised customers can only be viewed. */
    function menuItems(customer: Customer, { includeView = false } = {}): ActionMenuItem[] {
        const items: ActionMenuItem[] = [];

        if (includeView) {
            items.push({ key: 'view', label: t('admin.customers.view'), icon: 'users' });
        }

        if (customer.is_anonymised) {
            return items;
        }

        if (canUpdate.value) {
            items.push({ key: 'edit', label: t('admin.customers.edit'), icon: 'pen' });
        }

        if (canDeactivate.value) {
            items.push(
                customer.is_active
                    ? { key: 'deactivate', label: t('admin.customers.deactivate'), icon: 'ban' }
                    : { key: 'activate', label: t('admin.customers.activate'), icon: 'check' },
            );
        }

        if (canAnonymise.value) {
            items.push({
                key: 'anonymise',
                label: t('admin.customers.anonymise'),
                icon: 'eraser',
                danger: true,
                separated: true,
            });
        }

        return items;
    }

    /** Activating is harmless and reversible, so it runs without a confirmation dialog. */
    async function activate(customer: Customer): Promise<void> {
        activating.value = customer.id;

        try {
            const updated = await customersApi.setActive(customer.id, true);
            onUpdated(updated);
            toast.show(t('admin.customers.activated', { name: updated.name }));
        } catch (error) {
            toast.show(toApiError(error).message, 'danger');
        } finally {
            activating.value = null;
        }
    }

    function run(action: string, customer: Customer): void {
        if (action === 'activate') {
            void activate(customer);
        } else if (action === 'edit' || action === 'deactivate' || action === 'anonymise') {
            target.value = customer;
            dialog.value = action;
        }
    }

    function close(): void {
        dialog.value = null;
    }

    function finished(customer: Customer): void {
        const messages: Record<CustomerDialog, string> = {
            edit: t('admin.customers.saved'),
            deactivate: t('admin.customers.deactivated', { name: customer.name }),
            anonymise: t('admin.customers.anonymised'),
        };

        if (dialog.value) {
            toast.show(messages[dialog.value]);
        }

        onUpdated(customer);
        close();
    }

    return { dialog, target, activating, canUpdate, canDeactivate, canAnonymise, menuItems, run, close, finished };
}
