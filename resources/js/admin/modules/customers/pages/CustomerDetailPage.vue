<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { toApiError } from '@/shared/http/errors';
import ActionMenu from '@/shared/ui/ActionMenu.vue';
import AppAvatar from '@/shared/ui/AppAvatar.vue';
import AppButton from '@/shared/ui/AppButton.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import { Permission } from '../../../permissions';
import { activityLogApi } from '../../activity-log/api';
import ActivityEntry from '../../activity-log/components/ActivityEntry.vue';
import type { Activity } from '../../activity-log/types';
import { useAdminAuthStore } from '../../auth/store';
import { customersApi } from '../api';
import CustomerDialogs from '../components/CustomerDialogs.vue';
import CustomerStatusBadge from '../components/CustomerStatusBadge.vue';
import { useCustomerActions } from '../composables/useCustomerActions';
import type { Customer } from '../types';

const props = defineProps<{ id: number }>();

const { t } = useI18n();
const { localizeDigits, formatDate, formatDateTime, formatRelativeTime, formatNumber } = useLocale();
const auth = useAdminAuthStore();

const customer = ref<Customer | null>(null);
const error = ref<{ message: string; notFound: boolean } | null>(null);
const history = ref<Activity[] | null>(null);
const canSeeHistory = computed(() => auth.can(Permission.ViewActivityLog));

async function load(): Promise<void> {
    error.value = null;

    try {
        customer.value = await customersApi.show(props.id);
    } catch (caught) {
        const apiError = toApiError(caught);
        error.value = { message: apiError.message, notFound: apiError.status === 404 };

        return;
    }

    if (canSeeHistory.value) {
        history.value =
            (await activityLogApi.list({ subject_id: props.id, per_page: 10 }).catch(() => null))?.data ?? [];
    }
}

watch(() => props.id, load, { immediate: true });

const actions = useCustomerActions((updated) => {
    customer.value = updated;
    // Reload the history so the change just made shows up.
    if (canSeeHistory.value) {
        void activityLogApi
            .list({ subject_id: props.id, per_page: 10 })
            .then((result) => (history.value = result.data));
    }
});

/** On wide screens the main actions are buttons; the destructive one stays in the menu on phones. */
const menuItems = computed(() => (customer.value ? actions.menuItems(customer.value) : []));
const mainActions = computed(() => menuItems.value.filter((item) => item.key !== 'anonymise'));
const anonymiseAction = computed(() => menuItems.value.find((item) => item.key === 'anonymise'));
</script>

<template>
    <div class="mx-auto flex max-w-6xl flex-col gap-5">
        <RouterLink
            :to="{ name: 'customers' }"
            class="inline-flex w-fit items-center gap-1.5 rounded text-sm font-semibold text-primary hover:text-primary-hover focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
        >
            <AppIcon name="chevronNext" :size="16" flip-in-rtl class="rotate-180" />
            {{ t('admin.customers.back') }}
        </RouterLink>

        <div v-if="error" class="rounded-2xl border border-line bg-surface">
            <EmptyState v-if="error.notFound" icon="userX" :title="t('admin.customers.notFound')" />
            <div v-else class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center">
                <div class="grow"><FormAlert :message="error.message" /></div>
                <AppButton variant="secondary" size="md" @click="load">{{ t('common.retry') }}</AppButton>
            </div>
        </div>

        <div v-else-if="!customer" aria-hidden="true" class="flex flex-col gap-5">
            <div class="h-28 animate-pulse rounded-2xl bg-surface-muted motion-reduce:animate-none" />
            <div class="h-72 animate-pulse rounded-2xl bg-surface-muted motion-reduce:animate-none" />
        </div>

        <template v-else>
            <section
                class="flex flex-col gap-4 rounded-2xl border border-line bg-surface p-5 md:flex-row md:items-center"
            >
                <div class="flex grow items-center gap-4">
                    <AppAvatar :name="customer.name" size="lg" :anonymised="customer.is_anonymised" />
                    <div class="flex min-w-0 flex-col gap-1.5">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h1
                                :class="[
                                    'text-xl font-bold md:text-[22px]',
                                    customer.is_anonymised && 'text-fg-muted italic',
                                ]"
                            >
                                {{ customer.name }}
                            </h1>
                            <CustomerStatusBadge :customer="customer" />
                        </div>
                        <p class="text-sm text-fg-muted">
                            <template v-if="customer.phone_verified_at">
                                {{ t('admin.customers.since', { date: formatDate(customer.phone_verified_at) }) }}
                            </template>
                            <template v-if="customer.last_login_at">
                                ·
                                {{
                                    t('admin.customers.lastLogin', { when: formatRelativeTime(customer.last_login_at) })
                                }}
                            </template>
                        </p>
                    </div>
                </div>
                <div v-if="menuItems.length > 0" class="flex flex-wrap items-center gap-2.5">
                    <AppButton
                        v-for="item in mainActions"
                        :key="item.key"
                        variant="secondary"
                        size="md"
                        :loading="item.key === 'activate' && actions.activating.value === customer.id"
                        @click="actions.run(item.key, customer)"
                    >
                        <AppIcon :name="item.icon" :size="18" />
                        {{ item.label }}
                    </AppButton>
                    <AppButton
                        v-if="anonymiseAction"
                        variant="danger-outline"
                        size="md"
                        class="hidden md:inline-flex"
                        @click="actions.run('anonymise', customer)"
                    >
                        <AppIcon name="eraser" :size="18" />
                        {{ anonymiseAction.label }}
                    </AppButton>
                    <div v-if="anonymiseAction" class="md:hidden">
                        <ActionMenu
                            :label="t('admin.customers.more')"
                            :items="[anonymiseAction]"
                            @select="actions.run($event, customer)"
                        />
                    </div>
                </div>
            </section>

            <p
                v-if="customer.is_anonymised"
                role="note"
                class="flex items-center gap-2.5 rounded-xl bg-surface-muted px-4 py-3 text-sm text-fg-muted"
            >
                <AppIcon name="info" :size="18" />
                {{ t('admin.customers.anonymisedNotice') }}
            </p>

            <div class="grid gap-5 lg:grid-cols-3">
                <div class="flex flex-col gap-5 lg:col-span-2">
                    <section class="rounded-2xl border border-line bg-surface">
                        <h2 class="border-b border-line px-5 py-4 text-base font-bold">
                            {{ t('admin.customers.contact') }}
                        </h2>
                        <dl class="divide-y divide-line">
                            <div class="flex items-start gap-3 px-5 py-3.5">
                                <AppIcon name="phone" :size="18" class="mt-0.5 text-icon" />
                                <div class="flex flex-col gap-0.5">
                                    <dt class="text-xs text-fg-muted">{{ t('admin.customers.phone') }}</dt>
                                    <dd class="font-semibold">
                                        <span dir="ltr">{{
                                            customer.phone_number ? localizeDigits(customer.phone_number) : '—'
                                        }}</span>
                                    </dd>
                                    <dd
                                        v-if="!customer.is_anonymised"
                                        class="mt-1 flex items-center gap-1.5 text-xs text-fg-muted"
                                    >
                                        <AppIcon name="lock" :size="14" />
                                        {{ t('admin.customers.phoneLocked') }}
                                    </dd>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 px-5 py-3.5">
                                <AppIcon name="mail" :size="18" class="mt-0.5 text-icon" />
                                <div class="flex flex-col gap-0.5">
                                    <dt class="text-xs text-fg-muted">{{ t('admin.customers.email') }}</dt>
                                    <dd class="font-semibold">
                                        <span dir="ltr">{{ customer.email ?? '—' }}</span>
                                    </dd>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 px-5 py-3.5">
                                <AppIcon name="globe" :size="18" class="mt-0.5 text-icon" />
                                <div class="flex flex-col gap-0.5">
                                    <dt class="text-xs text-fg-muted">{{ t('admin.customers.language') }}</dt>
                                    <dd class="font-semibold">
                                        {{
                                            customer.locale === 'fa'
                                                ? t('admin.customers.languageFa')
                                                : t('admin.customers.languageEn')
                                        }}
                                    </dd>
                                </div>
                            </div>
                        </dl>
                    </section>

                    <section class="rounded-2xl border border-line bg-surface">
                        <div class="flex items-center justify-between border-b border-line px-5 py-4">
                            <h2 class="text-base font-bold">{{ t('admin.customers.orders') }}</h2>
                            <span
                                class="rounded-full bg-surface-muted px-2.5 py-0.5 text-xs font-semibold text-fg-muted"
                            >
                                {{ t('admin.nav.soon') }}
                            </span>
                        </div>
                        <EmptyState icon="cart" :title="t('admin.customers.ordersSoon')" />
                    </section>
                </div>

                <div class="flex flex-col gap-5">
                    <section class="rounded-2xl border border-line bg-surface">
                        <h2 class="border-b border-line px-5 py-4 text-base font-bold">
                            {{ t('admin.customers.account') }}
                        </h2>
                        <dl class="grid grid-cols-2 gap-x-3 gap-y-3.5 px-5 py-4 text-sm">
                            <dt class="text-fg-muted">{{ t('admin.customers.id') }}</dt>
                            <dd class="font-semibold">#{{ formatNumber(customer.id) }}</dd>
                            <dt class="text-fg-muted">{{ t('admin.customers.registeredAt') }}</dt>
                            <dd class="font-semibold">
                                {{ customer.phone_verified_at ? formatDate(customer.phone_verified_at) : '—' }}
                            </dd>
                            <dt class="text-fg-muted">{{ t('admin.customers.lastLoginAt') }}</dt>
                            <dd class="font-semibold">
                                <time
                                    v-if="customer.last_login_at"
                                    :datetime="customer.last_login_at"
                                    :title="formatDateTime(customer.last_login_at)"
                                >
                                    {{ formatRelativeTime(customer.last_login_at) }}
                                </time>
                                <template v-else>—</template>
                            </dd>
                            <dt class="text-fg-muted">{{ t('admin.customers.phoneVerified') }}</dt>
                            <dd class="font-semibold">
                                {{
                                    customer.registration_completed
                                        ? t('admin.customers.verified')
                                        : t('admin.customers.notVerified')
                                }}
                            </dd>
                        </dl>
                    </section>

                    <section v-if="canSeeHistory" class="rounded-2xl border border-line bg-surface">
                        <h2 class="border-b border-line px-5 py-4 text-base font-bold">
                            {{ t('admin.customers.history') }}
                        </h2>
                        <div
                            v-if="history === null"
                            aria-hidden="true"
                            class="m-5 h-24 animate-pulse rounded-xl bg-surface-muted"
                        />
                        <p v-else-if="history.length === 0" class="px-5 py-6 text-sm text-fg-muted">
                            {{ t('admin.customers.noHistory') }}
                        </p>
                        <ul v-else class="divide-y divide-line px-5">
                            <li v-for="entry in history" :key="entry.id">
                                <ActivityEntry :activity="entry" compact />
                            </li>
                        </ul>
                    </section>
                </div>
            </div>
        </template>

        <CustomerDialogs
            :dialog="actions.dialog.value"
            :customer="actions.target.value"
            @close="actions.close"
            @finished="actions.finished"
        />
    </div>
</template>
