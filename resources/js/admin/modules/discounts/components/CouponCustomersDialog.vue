<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/shared/composables/useLocale';
import { toApiError } from '@/shared/http/errors';
import type { Paginated } from '@/shared/types/api';
import AppButton from '@/shared/ui/AppButton.vue';
import AppDialog from '@/shared/ui/AppDialog.vue';
import AppIcon from '@/shared/ui/AppIcon.vue';
import AppPagination from '@/shared/ui/AppPagination.vue';
import AppSpinner from '@/shared/ui/AppSpinner.vue';
import FormAlert from '@/shared/ui/FormAlert.vue';
import SearchInput from '@/shared/ui/SearchInput.vue';
import { customersApi } from '../../customers/api';
import type { Customer } from '../../customers/types';
import type { CouponCustomer } from '../types';

/**
 * "Add customers" of the coupon editor: active customers found by name, phone number or email.
 * Customers already allowed are marked.
 */
const props = defineProps<{ open: boolean; chosenIds: Set<number> }>();
const emit = defineEmits<{ close: []; add: [customers: CouponCustomer[]] }>();

const { t } = useI18n();
const { formatNumber, formatDate, localizeDigits } = useLocale();

const picked = ref<Map<number, CouponCustomer>>(new Map());
const error = ref<string | null>(null);

const query = ref('');
const page = ref(1);
const result = ref<Paginated<Customer> | null>(null);
const loading = ref(false);

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        result.value = await customersApi.list({
            q: query.value || undefined,
            status: 'active',
            registration: 'completed',
            page: page.value,
            per_page: 20,
        });
    } catch (caught) {
        error.value = toApiError(caught).message;
    } finally {
        loading.value = false;
    }
}

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        picked.value = new Map();
        query.value = '';
        page.value = 1;
        void load();
    },
);

watch(query, () => {
    page.value = 1;
    void load();
});

function goTo(next: number): void {
    page.value = next;
    void load();
}

const asCouponCustomer = (customer: Customer): CouponCustomer => ({
    id: customer.id,
    name: customer.name,
    phone_number: customer.phone_number ?? '',
    used_count: 0,
});

function toggle(customer: Customer): void {
    const next = new Map(picked.value);

    if (next.has(customer.id)) {
        next.delete(customer.id);
    } else {
        next.set(customer.id, asCouponCustomer(customer));
    }

    picked.value = next;
}

function unpick(id: number): void {
    const next = new Map(picked.value);
    next.delete(id);
    picked.value = next;
}

const rowClass = (on: boolean, locked: boolean): string[] => [
    'flex items-center gap-3 px-3.5 py-2.5',
    locked ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:bg-surface-muted/50',
    on && !locked ? 'bg-primary-soft/40' : '',
];
</script>

<template>
    <AppDialog
        :open="open"
        :title="t('admin.discounts.coupons.customers.title')"
        icon="users"
        size="lg"
        @close="emit('close')"
    >
        <div class="flex flex-col gap-4">
            <p class="text-sm leading-relaxed text-fg-muted">{{ t('admin.discounts.coupons.customers.intro') }}</p>

            <FormAlert v-if="error" :message="error" />

            <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_14rem]">
                <div class="flex min-w-0 flex-col gap-3">
                    <SearchInput v-model="query" :label="t('admin.discounts.coupons.customers.searchLabel')" />
                    <div class="max-h-[22rem] overflow-y-auto rounded-xl border border-line">
                        <div v-if="!result" class="flex justify-center py-10"><AppSpinner /></div>
                        <p v-else-if="result.data.length === 0" class="px-4 py-8 text-center text-sm text-fg-muted">
                            {{ t('admin.discounts.coupons.customers.noMatches') }}
                        </p>
                        <ul v-else :aria-busy="loading" :class="['divide-y divide-line', loading && 'opacity-60']">
                            <li v-for="customer in result.data" :key="customer.id">
                                <label :class="rowClass(picked.has(customer.id), chosenIds.has(customer.id))">
                                    <input
                                        type="checkbox"
                                        class="size-4 shrink-0 accent-[var(--color-primary)]"
                                        :checked="picked.has(customer.id) || chosenIds.has(customer.id)"
                                        :disabled="chosenIds.has(customer.id)"
                                        @change="toggle(customer)"
                                    />
                                    <span class="flex min-w-0 grow flex-col">
                                        <span class="truncate text-sm font-semibold">{{ customer.name }}</span>
                                        <span class="truncate text-xs text-fg-muted">{{
                                            chosenIds.has(customer.id)
                                                ? t('admin.discounts.coupons.customers.already')
                                                : customer.created_at
                                                  ? t('admin.discounts.coupons.customers.since', {
                                                        date: formatDate(customer.created_at),
                                                    })
                                                  : ''
                                        }}</span>
                                    </span>
                                    <span dir="ltr" class="shrink-0 text-xs text-fg-muted">{{
                                        localizeDigits(customer.phone_number ?? '')
                                    }}</span>
                                </label>
                            </li>
                        </ul>
                        <AppPagination v-if="result && result.meta.last_page > 1" :meta="result.meta" @change="goTo" />
                    </div>
                </div>

                <div class="flex flex-col gap-2.5 rounded-xl bg-surface-muted/50 p-3.5">
                    <span class="text-[13px] font-semibold">{{
                        t('admin.discounts.coupons.customers.picked', { n: formatNumber(picked.size) })
                    }}</span>
                    <ul class="flex max-h-[20rem] flex-col gap-1.5 overflow-y-auto">
                        <li
                            v-for="customer in picked.values()"
                            :key="customer.id"
                            class="flex items-center justify-between gap-2 rounded-lg border border-line bg-surface ps-2.5 text-[13px]"
                        >
                            <span class="truncate">{{ customer.name }}</span>
                            <button
                                type="button"
                                class="flex size-8 shrink-0 items-center justify-center rounded-lg text-fg-muted hover:text-fg focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                :aria-label="t('admin.discounts.coupons.customers.unpick', { name: customer.name })"
                                @click="unpick(customer.id)"
                            >
                                <AppIcon name="close" :size="14" />
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <template #footer>
            <AppButton size="md" :disabled="picked.size === 0" @click="emit('add', [...picked.values()])">{{
                t('admin.discounts.coupons.customers.add', { n: formatNumber(picked.size) })
            }}</AppButton>
            <AppButton variant="secondary" size="md" @click="emit('close')">{{ t('common.cancel') }}</AppButton>
        </template>
    </AppDialog>
</template>
