<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useLocale } from '@/shared/composables/useLocale';
import { toApiError } from '@/shared/http/errors';
import type { Paginated } from '@/shared/types/api';
import ActionMenu from '@/shared/ui/ActionMenu.vue';
import AppAvatar from '@/shared/ui/AppAvatar.vue';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppPagination from '@/shared/ui/AppPagination.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SearchInput from '@/shared/ui/SearchInput.vue';
import SegmentedControl from '@/shared/ui/SegmentedControl.vue';
import SelectField from '@/shared/ui/SelectField.vue';
import { customersApi } from '../api';
import CustomerDialogs from '../components/CustomerDialogs.vue';
import CustomerStatusBadge from '../components/CustomerStatusBadge.vue';
import { useCustomerActions } from '../composables/useCustomerActions';
import type { Customer, CustomerFilters } from '../types';

type StatusFilter = 'all' | 'active' | 'inactive' | 'anonymised';
type RegistrationFilter = 'all' | 'completed' | 'pending';

const { t } = useI18n();
const { localizeDigits, formatDate, formatRelativeTime, formatNumber } = useLocale();
const route = useRoute();
const router = useRouter();

const result = ref<Paginated<Customer> | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

// Filters live in the URL so a filtered list can be bookmarked and survives back/forward.
const queryString = (key: string): string => (typeof route.query[key] === 'string' ? route.query[key] : '');

const search = computed({
    get: () => queryString('q'),
    set: (q: string) => updateQuery({ q: q || undefined }),
});
const status = computed<StatusFilter>({
    get: () =>
        ['active', 'inactive', 'anonymised'].includes(queryString('status'))
            ? (queryString('status') as StatusFilter)
            : 'all',
    set: (value) => updateQuery({ status: value === 'all' ? undefined : value }),
});
const registration = computed<RegistrationFilter>({
    get: () =>
        ['completed', 'pending'].includes(queryString('registration'))
            ? (queryString('registration') as RegistrationFilter)
            : 'all',
    set: (value) => updateQuery({ registration: value === 'all' ? undefined : value }),
});
const page = computed(() => Math.max(1, Number(queryString('page')) || 1));

/** Any filter change goes back to the first page. */
function updateQuery(changes: Record<string, string | undefined>, keepPage = false): void {
    void router.replace({ query: { ...route.query, ...changes, ...(keepPage ? {} : { page: undefined }) } });
}

const statusOptions = computed(() => [
    { value: 'all' as const, label: t('admin.customers.all') },
    { value: 'active' as const, label: t('admin.customers.statusActive') },
    { value: 'inactive' as const, label: t('admin.customers.statusInactive') },
    { value: 'anonymised' as const, label: t('admin.customers.statusAnonymised') },
]);
const registrationOptions = computed(() => [
    { value: 'all' as const, label: t('admin.customers.all') },
    { value: 'completed' as const, label: t('admin.customers.registrationCompleted') },
    { value: 'pending' as const, label: t('admin.customers.registrationPending') },
]);

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    const filters: CustomerFilters = {
        q: search.value || undefined,
        status: status.value === 'all' ? undefined : status.value,
        registration: registration.value === 'all' ? undefined : registration.value,
        page: page.value,
    };

    try {
        result.value = await customersApi.list(filters);
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

watch(() => [route.query.q, route.query.status, route.query.registration, route.query.page], load, { immediate: true });

const actions = useCustomerActions((updated) => {
    if (result.value) {
        result.value.data = result.value.data.map((customer) => (customer.id === updated.id ? updated : customer));
    }
});

function onMenu(key: string, customer: Customer): void {
    if (key === 'view') {
        void router.push({ name: 'customer', params: { id: customer.id } });
    } else {
        actions.run(key, customer);
    }
}
</script>

<template>
    <div class="mx-auto flex max-w-6xl flex-col gap-5">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-bold">{{ t('admin.customers.title') }}</h1>
            <p v-if="result" class="text-sm text-fg-muted">
                {{ t('admin.customers.count', { n: formatNumber(result.meta.total) }) }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <SearchInput v-model="search" :label="t('admin.customers.search')" />
            <div class="max-w-full overflow-x-auto">
                <SegmentedControl v-model="status" :label="t('admin.customers.status')" :options="statusOptions" />
            </div>
            <SelectField
                v-model="registration"
                :label="t('admin.customers.registration')"
                :options="registrationOptions"
            />
        </div>

        <div v-if="error" class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="grow"><FormAlert :message="error" /></div>
            <AppButton variant="secondary" size="md" :loading="loading" @click="load">
                <AppIcon name="refresh" :size="18" />
                {{ t('common.retry') }}
            </AppButton>
        </div>

        <section
            v-else
            :aria-busy="loading"
            :class="[
                'overflow-hidden rounded-2xl border border-line bg-surface transition-opacity',
                loading && result && 'opacity-60',
            ]"
        >
            <div v-if="!result" aria-hidden="true" class="flex flex-col gap-px">
                <div
                    v-for="row in 8"
                    :key="row"
                    class="h-16 animate-pulse bg-surface-muted/60 motion-reduce:animate-none"
                />
            </div>

            <EmptyState
                v-else-if="result.data.length === 0"
                icon="search"
                :title="t('admin.customers.empty')"
                :description="t('admin.customers.emptyHint')"
            />

            <template v-else>
                <!-- Tables need room; phones get a card list instead. -->
                <table class="hidden w-full md:table">
                    <thead class="bg-bg text-xs font-semibold text-fg-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-start">{{ t('admin.customers.colCustomer') }}</th>
                            <th scope="col" class="px-4 py-3 text-start">{{ t('admin.customers.colPhone') }}</th>
                            <th scope="col" class="px-4 py-3 text-start">{{ t('admin.customers.colRegistered') }}</th>
                            <th scope="col" class="px-4 py-3 text-start">{{ t('admin.customers.colLastLogin') }}</th>
                            <th scope="col" class="px-4 py-3 text-start">{{ t('admin.customers.colStatus') }}</th>
                            <th scope="col" class="w-14 px-3 py-3">
                                <span class="sr-only">{{ t('admin.customers.colActions') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="customer in result.data" :key="customer.id" class="hover:bg-surface-muted/40">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <AppAvatar :name="customer.name" size="sm" :anonymised="customer.is_anonymised" />
                                    <div class="flex min-w-0 flex-col">
                                        <RouterLink
                                            :to="{ name: 'customer', params: { id: customer.id } }"
                                            :class="[
                                                'truncate rounded text-sm font-semibold hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none',
                                                customer.is_anonymised ? 'text-fg-muted italic' : 'text-fg',
                                            ]"
                                        >
                                            {{ customer.name }}
                                        </RouterLink>
                                        <span dir="ltr" class="truncate text-start text-xs text-fg-muted rtl:text-end">
                                            {{
                                                customer.email ??
                                                (customer.is_anonymised ? '—' : t('admin.customers.noEmail'))
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap">
                                <span dir="ltr">{{
                                    customer.phone_number ? localizeDigits(customer.phone_number) : '—'
                                }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap text-fg-muted">
                                {{ customer.phone_verified_at ? formatDate(customer.phone_verified_at) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap text-fg-muted">
                                {{ customer.last_login_at ? formatRelativeTime(customer.last_login_at) : '—' }}
                            </td>
                            <td class="px-4 py-3"><CustomerStatusBadge :customer="customer" /></td>
                            <td class="px-3 py-2">
                                <ActionMenu
                                    :label="t('admin.customers.actionsFor', { name: customer.name })"
                                    :items="actions.menuItems(customer, { includeView: true })"
                                    @select="onMenu($event, customer)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>

                <ul class="divide-y divide-line md:hidden">
                    <li v-for="customer in result.data" :key="customer.id">
                        <RouterLink
                            :to="{ name: 'customer', params: { id: customer.id } }"
                            class="flex items-center gap-3 px-4 py-3.5 focus-visible:bg-surface-muted focus-visible:outline-none"
                        >
                            <AppAvatar :name="customer.name" :anonymised="customer.is_anonymised" />
                            <span class="min-w-0 grow">
                                <span
                                    :class="[
                                        'block truncate font-semibold',
                                        customer.is_anonymised ? 'text-fg-muted italic' : 'text-fg',
                                    ]"
                                >
                                    {{ customer.name }}
                                </span>
                                <span dir="ltr" class="block truncate text-start text-sm text-fg-muted rtl:text-end">
                                    {{ customer.phone_number ? localizeDigits(customer.phone_number) : '—' }}
                                </span>
                            </span>
                            <span class="flex shrink-0 flex-col items-end gap-1.5">
                                <CustomerStatusBadge :customer="customer" />
                                <span class="text-xs text-fg-muted">
                                    {{ customer.phone_verified_at ? formatDate(customer.phone_verified_at) : '' }}
                                </span>
                            </span>
                        </RouterLink>
                    </li>
                </ul>

                <AppPagination :meta="result.meta" @change="updateQuery({ page: String($event) }, true)" />
            </template>
        </section>

        <CustomerDialogs
            :dialog="actions.dialog.value"
            :customer="actions.target.value"
            @close="actions.close"
            @finished="actions.finished"
        />
    </div>
</template>
