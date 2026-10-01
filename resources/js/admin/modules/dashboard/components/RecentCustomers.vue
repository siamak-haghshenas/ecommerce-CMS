<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import AppAvatar from '@/shared/ui/AppAvatar.vue';
import AppBadge from '@/shared/ui/AppBadge.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import type { Customer } from '../../customers/types';

defineProps<{ customers: Customer[] }>();

const { t } = useI18n();
const { localizeDigits, formatDateTime, formatRelativeTime } = useLocale();
</script>

<template>
    <section class="rounded-2xl border border-line bg-surface">
        <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
            <h2 class="text-base font-bold">{{ t('admin.dashboard.recentCustomers') }}</h2>
            <RouterLink
                v-if="customers.length > 0"
                :to="{ name: 'customers' }"
                class="rounded text-sm font-semibold text-primary hover:text-primary-hover focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            >
                {{ t('admin.dashboard.viewAll') }}
            </RouterLink>
        </div>

        <EmptyState
            v-if="customers.length === 0"
            icon="users"
            :title="t('admin.dashboard.noCustomers')"
            :description="t('admin.dashboard.noCustomersHint')"
        />

        <ul v-else class="divide-y divide-line">
            <li v-for="customer in customers" :key="customer.id">
                <RouterLink
                    :to="{ name: 'customer', params: { id: customer.id } }"
                    class="flex items-center gap-3 px-5 py-3.5 hover:bg-surface-muted/60 focus-visible:bg-surface-muted focus-visible:outline-none"
                >
                    <AppAvatar :name="customer.name" />
                    <span class="min-w-0 grow">
                        <span class="block truncate font-semibold text-fg">{{ customer.name }}</span>
                        <span dir="ltr" class="block truncate text-start text-sm text-fg-muted rtl:text-end">
                            {{ customer.phone_number ? localizeDigits(customer.phone_number) : '—' }}
                        </span>
                    </span>
                    <span class="flex shrink-0 flex-col items-end gap-1">
                        <AppBadge :tone="customer.is_active ? 'success' : 'danger'">
                            {{
                                customer.is_active
                                    ? t('admin.customers.statusActive')
                                    : t('admin.customers.statusInactive')
                            }}
                        </AppBadge>
                        <time
                            v-if="customer.phone_verified_at"
                            :datetime="customer.phone_verified_at"
                            :title="formatDateTime(customer.phone_verified_at)"
                            class="text-xs text-fg-muted"
                        >
                            {{ formatRelativeTime(customer.phone_verified_at) }}
                        </time>
                    </span>
                </RouterLink>
            </li>
        </ul>
    </section>
</template>
