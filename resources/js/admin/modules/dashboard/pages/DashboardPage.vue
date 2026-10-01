<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { toApiError } from '@/shared/http/errors';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import { Permission } from '../../../permissions';
import { useAdminAuthStore } from '../../auth/store';
import { dashboardApi } from '../api';
import RecentCustomers from '../components/RecentCustomers.vue';
import StatCard from '../components/StatCard.vue';
import type { DashboardSummary } from '../types';

const { t } = useI18n();
const { formatNumber, formatDate } = useLocale();
const auth = useAdminAuthStore();

/** The figures include customer data, so they need the customer permission (authors see a welcome only). */
const canSeeFigures = computed(() => auth.can(Permission.ViewCustomers));

const summary = ref<DashboardSummary | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

const today = computed(() =>
    formatDate(new Date(), { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
);

const newCustomersTrend = computed(() => {
    if (!summary.value) {
        return undefined;
    }

    const { customers_new_this_week: thisWeek, customers_new_previous_week: previousWeek } = summary.value.stats;
    const difference = thisWeek - previousWeek;

    if (difference === 0) {
        return { direction: 'same' as const, text: t('admin.dashboard.trendSame') };
    }

    return difference > 0
        ? { direction: 'up' as const, text: t('admin.dashboard.trendUp', { n: formatNumber(difference) }) }
        : { direction: 'down' as const, text: t('admin.dashboard.trendDown', { n: formatNumber(-difference) }) };
});

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        summary.value = await dashboardApi.summary();
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    if (canSeeFigures.value) {
        void load();
    }
});
</script>

<template>
    <div class="mx-auto flex max-w-6xl flex-col gap-6">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-bold">{{ t('admin.dashboard.greeting', { name: auth.user?.name ?? '' }) }}</h1>
            <p class="text-sm text-fg-muted">{{ today }}</p>
        </div>

        <p
            v-if="!canSeeFigures"
            class="rounded-2xl border border-line bg-surface p-5 text-sm leading-relaxed text-fg-muted"
        >
            {{ t('admin.dashboard.authorWelcome') }}
        </p>

        <div v-else-if="error" class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="grow"><FormAlert :message="error" /></div>
            <AppButton variant="secondary" size="md" :loading="loading" @click="load">
                <AppIcon name="refresh" :size="18" />
                {{ t('common.retry') }}
            </AppButton>
        </div>

        <!-- Same shape as the content, so nothing jumps when data arrives. -->
        <div v-else-if="!summary" aria-hidden="true" class="flex flex-col gap-6">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="card in 4"
                    :key="card"
                    class="h-40 animate-pulse rounded-2xl bg-surface-muted motion-reduce:animate-none"
                />
            </div>
            <div class="h-80 animate-pulse rounded-2xl bg-surface-muted motion-reduce:animate-none" />
        </div>

        <template v-else>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    :label="t('admin.dashboard.customersTotal')"
                    :value="formatNumber(summary.stats.customers_total)"
                    icon="users"
                    :hint="t('admin.dashboard.customersTotalHint')"
                />
                <StatCard
                    :label="t('admin.dashboard.newCustomers')"
                    :value="formatNumber(summary.stats.customers_new_this_week)"
                    icon="userPlus"
                    :hint="t('admin.dashboard.newCustomersHint')"
                    :trend="newCustomersTrend"
                />
                <StatCard
                    :label="t('admin.dashboard.pending')"
                    :value="formatNumber(summary.stats.pending_registrations)"
                    icon="clock"
                    :hint="t('admin.dashboard.pendingHint')"
                />
                <StatCard
                    :label="t('admin.dashboard.staff')"
                    :value="formatNumber(summary.stats.staff_total)"
                    icon="shieldCheck"
                    :hint="t('admin.dashboard.staffHint')"
                />
            </div>

            <RecentCustomers :customers="summary.recent_customers" />
        </template>
    </div>
</template>
